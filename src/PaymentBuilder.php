<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Http\Client\ConnectionException;
use ZoweSoft\LaravelCredo\Enums\Channel;
use ZoweSoft\LaravelCredo\Enums\Currency;
use ZoweSoft\LaravelCredo\Enums\FeeBearer;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;

/**
 * Fluent builder for the Credo initialize-transaction payload.
 *
 * Covers every documented field: amount (lowest or major units), currency,
 * fee bearer, channels, virtual account generation, business reference,
 * callback URL, customer details, narration, metadata/custom fields and the
 * settlement options (serviceCode, splitConfiguration, pauseSettlement).
 * Create via Credo::payment() and send with send().
 *
 * @see https://docs.credocentral.com/docs/developers/accept-payments (Required and optional fields)
 * @see https://docs.credocentral.com/docs/guides/settlement-system
 */
class PaymentBuilder
{
    /** @var array<int, string> Selected channel values (CARD, BANK); empty means let Credo show all. */
    protected array $channels = [];

    /** @var array<string, mixed>|null Custom data echoed back in verify responses and webhooks. */
    protected ?array $metadata = null;

    /** @var array<string, mixed>|null Dynamic split settlement configuration. */
    protected ?array $splitConfiguration = null;

    /** Service code of a split settlement rule pre-configured in the Credo dashboard. */
    protected ?string $serviceCode = null;

    /** Description shown on the payment page. */
    protected ?string $narration = null;

    /** Optional customer details displayed/used by Credo. */
    protected ?string $customerFirstName = null;

    /** Optional customer details displayed/used by Credo. */
    protected ?string $customerLastName = null;

    /** Optional customer details displayed/used by Credo. */
    protected ?string $customerPhoneNumber = null;

    /** Your unique business reference; Credo generates one when omitted. */
    protected ?string $reference = null;

    /** Where Credo redirects the customer after payment; falls back to credo.callback_url. */
    protected ?string $callbackUrl = null;

    /** 1 when funds should be held in escrow instead of settling normally. */
    protected ?int $pauseSettlement = null;

    /** Date (Y-m-d) when paused funds will settle. */
    protected ?string $pauseSettlementDate = null;

    /** Whether Credo should generate a virtual account for bank transfer. */
    protected bool $initializeAccount = false;

    /**
     * @param  CredoManager  $manager  Manager used by send() to dispatch the request.
     * @param  int  $amount  Amount in the lowest currency unit (kobo for NGN).
     * @param  string  $email  Customer email address.
     * @param  Currency  $currency  Charge currency.
     * @param  FeeBearer  $bearer  Who pays the processing fee.
     */
    public function __construct(
        protected CredoManager $manager,
        protected int $amount = 0,
        protected string $email = '',
        protected Currency $currency = Currency::NGN,
        protected FeeBearer $bearer = FeeBearer::CUSTOMER,
    ) {}

    /**
     * Amount in the lowest currency unit (kobo for NGN, cents for USD).
     *
     * @see https://docs.credocentral.com/docs/concepts#amounts (Amounts)
     */
    public function amount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Amount in the major currency unit (e.g. naira); converted to kobo.
     * Convenience mirroring how verify responses report amounts.
     */
    public function amountInMajorUnits(float $amount): static
    {
        return $this->amount((int) round($amount * 100));
    }

    /**
     * Customer email address (required by Credo).
     */
    public function email(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Charge currency (NGN or USD).
     */
    public function currency(Currency|string $currency): static
    {
        $this->currency = $currency instanceof Currency ? $currency : Currency::from($currency);

        return $this;
    }

    /**
     * Who bears the transaction fee: 0 customer, 1 merchant.
     *
     * @see https://docs.credocentral.com/docs/concepts#fee-bearer (Fee bearer)
     */
    public function bearer(FeeBearer|int $bearer): static
    {
        $this->bearer = $bearer instanceof FeeBearer ? $bearer : FeeBearer::from($bearer);

        return $this;
    }

    /**
     * Fee is added on top: customer pays more, you receive the full amount.
     */
    public function customerBearsFee(): static
    {
        return $this->bearer(FeeBearer::CUSTOMER);
    }

    /**
     * Fee is deducted from the amount: you receive less.
     */
    public function merchantBearsFee(): static
    {
        return $this->bearer(FeeBearer::MERCHANT);
    }

    /**
     * Restrict the payment methods offered on the checkout page.
     *
     * @param  array<int, Channel|string>  $channels  e.g. [Channel::CARD, 'bank'].
     */
    public function channels(array $channels): static
    {
        $this->channels = array_values(array_map(
            fn (Channel|string $channel) => $channel instanceof Channel ? $channel->value : strtoupper($channel),
            $channels,
        ));

        return $this;
    }

    /**
     * Offer card payments (Visa, Mastercard, Verve) on the checkout.
     */
    public function card(): static
    {
        return $this->withChannel(Channel::CARD);
    }

    /**
     * Offer direct bank transfers on the checkout.
     */
    public function bank(): static
    {
        return $this->withChannel(Channel::BANK);
    }

    /**
     * Add one channel to the selection (idempotent).
     */
    public function withChannel(Channel|string $channel): static
    {
        $value = $channel instanceof Channel ? $channel->value : strtoupper($channel);

        if (! in_array($value, $this->channels, true)) {
            $this->channels[] = $value;
        }

        return $this;
    }

    /**
     * Generate a virtual account for the customer to transfer into
     * (initializeAccount=1).
     *
     * @see https://docs.credocentral.com/docs/developers/testing (Testing bank transfers)
     */
    public function generateVirtualAccount(): static
    {
        $this->initializeAccount = true;

        return $this;
    }

    /**
     * Your unique, alphanumeric business reference for reconciliation
     * (returned as businessRef in verify responses and webhooks).
     */
    public function reference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * Where Credo redirects the customer after payment. The redirect is
     * informational only — always verify server-side.
     */
    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * Set the customer name and phone number in one call.
     */
    public function customer(string $firstName, ?string $lastName = null, ?string $phoneNumber = null): static
    {
        $this->customerFirstName = $firstName;
        $this->customerLastName = $lastName ?? $this->customerLastName;
        $this->customerPhoneNumber = $phoneNumber ?? $this->customerPhoneNumber;

        return $this;
    }

    /**
     * Customer first name shown to Credo.
     */
    public function firstName(string $firstName): static
    {
        $this->customerFirstName = $firstName;

        return $this;
    }

    /**
     * Customer last name shown to Credo.
     */
    public function lastName(string $lastName): static
    {
        $this->customerLastName = $lastName;

        return $this;
    }

    /**
     * Customer phone number shown to Credo.
     */
    public function phoneNumber(string $phoneNumber): static
    {
        $this->customerPhoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Description shown on the payment page.
     */
    public function narration(string $narration): static
    {
        $this->narration = $narration;

        return $this;
    }

    /**
     * Attach arbitrary custom data, returned in verify/webhook payloads.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Attach a custom field in the shape Credo's checkout displays
     * (metadata.customFields[] with display_name/variable_name/value).
     */
    public function customField(string $displayName, string $variableName, string $value): static
    {
        $this->metadata ??= ['customFields' => []];
        $this->metadata['customFields'][] = [
            'display_name' => $displayName,
            'variable_name' => $variableName,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Route proceeds through a split settlement rule pre-configured in the
     * Credo dashboard.
     *
     * @see https://docs.credocentral.com/docs/guides/settlement-system
     */
    public function serviceCode(string $serviceCode): static
    {
        $this->serviceCode = $serviceCode;

        return $this;
    }

    /**
     * Configure split settlement dynamically at transaction time, instead of
     * a dashboard-defined serviceCode.
     *
     * @param  array<string, mixed>  $splitConfiguration
     *
     * @see https://docs.credocentral.com/docs/guides/settlement-system
     */
    public function splitConfiguration(array $splitConfiguration): static
    {
        $this->splitConfiguration = $splitConfiguration;

        return $this;
    }

    /**
     * Hold funds in escrow until the given date (or until released).
     *
     * @param  string|null  $date  Y-m-d when the funds should settle.
     *
     * @see https://docs.credocentral.com/docs/guides/settlement-system
     */
    public function pauseSettlement(?string $date = null): static
    {
        $this->pauseSettlement = 1;

        if ($date !== null) {
            $this->pauseSettlementDate = $date;
        }

        return $this;
    }

    /**
     * Build the initialize payload; per-mode callback default is applied
     * by the manager at request time so config changes always win.
     *
     * @return array<string, mixed>
     *
     * @see https://docs.credocentral.com/docs/reference/transactions/initializeTransaction
     */
    public function toArray(): array
    {
        $payload = [
            'amount' => $this->amount,
            'email' => $this->email,
            'currency' => $this->currency->value,
            'bearer' => $this->bearer->value,
        ];

        if ($this->channels !== []) {
            $payload['channels'] = $this->channels;
        }

        if ($this->initializeAccount) {
            $payload['initializeAccount'] = 1;
        }

        foreach (['reference', 'callbackUrl', 'customerFirstName', 'customerLastName', 'customerPhoneNumber', 'narration', 'metadata', 'serviceCode', 'splitConfiguration'] as $property) {
            if (($value = $this->{$property}) !== null) {
                $payload[$property] = $value;
            }
        }

        if ($this->pauseSettlement !== null) {
            $payload['pauseSettlement'] = $this->pauseSettlement;
        }

        if ($this->pauseSettlementDate !== null) {
            $payload['pauseSettlementDate'] = $this->pauseSettlementDate;
        }

        return $payload;
    }

    /**
     * Send the payment to Credo and return the checkout details.
     *
     * @throws InvalidConfigurationException When keys are missing or malformed.
     * @throws RequestFailedException When Credo rejects the request.
     * @throws ConnectionException When every attempt fails to connect.
     */
    public function send(): InitializeResponse
    {
        return $this->manager->initialize($this);
    }
}
