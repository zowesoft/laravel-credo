<?php

namespace ZoweSoft\LaravelCredo;

use ZoweSoft\LaravelCredo\Enums\Channel;
use ZoweSoft\LaravelCredo\Enums\Currency;
use ZoweSoft\LaravelCredo\Enums\FeeBearer;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;

class PaymentBuilder
{
    /** @var array<int, string> */
    protected array $channels = [];

    /** @var array<string, mixed>|null */
    protected ?array $metadata = null;

    /** @var array<string, mixed>|null */
    protected ?array $splitConfiguration = null;

    protected ?string $serviceCode = null;

    protected ?string $narration = null;

    protected ?string $customerFirstName = null;

    protected ?string $customerLastName = null;

    protected ?string $customerPhoneNumber = null;

    protected ?string $reference = null;

    protected ?string $callbackUrl = null;

    protected ?int $pauseSettlement = null;

    protected ?string $pauseSettlementDate = null;

    protected bool $initializeAccount = false;

    public function __construct(
        protected CredoManager $manager,
        protected int $amount = 0,
        protected string $email = '',
        protected Currency $currency = Currency::NGN,
        protected FeeBearer $bearer = FeeBearer::CUSTOMER,
    ) {}

    /**
     * Amount in the lowest currency unit (kobo for NGN).
     */
    public function amount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Amount in the major currency unit (e.g. naira); converted to kobo.
     */
    public function amountInMajorUnits(float $amount): static
    {
        return $this->amount((int) round($amount * 100));
    }

    public function email(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function currency(Currency|string $currency): static
    {
        $this->currency = $currency instanceof Currency ? $currency : Currency::from($currency);

        return $this;
    }

    public function bearer(FeeBearer|int $bearer): static
    {
        $this->bearer = $bearer instanceof FeeBearer ? $bearer : FeeBearer::from($bearer);

        return $this;
    }

    public function customerBearsFee(): static
    {
        return $this->bearer(FeeBearer::CUSTOMER);
    }

    public function merchantBearsFee(): static
    {
        return $this->bearer(FeeBearer::MERCHANT);
    }

    /**
     * @param  array<int, Channel|string>  $channels
     */
    public function channels(array $channels): static
    {
        $this->channels = array_values(array_map(
            fn (Channel|string $channel) => $channel instanceof Channel ? $channel->value : strtoupper($channel),
            $channels,
        ));

        return $this;
    }

    public function card(): static
    {
        return $this->withChannel(Channel::CARD);
    }

    public function bank(): static
    {
        return $this->withChannel(Channel::BANK);
    }

    public function withChannel(Channel|string $channel): static
    {
        $value = $channel instanceof Channel ? $channel->value : strtoupper($channel);

        if (! in_array($value, $this->channels, true)) {
            $this->channels[] = $value;
        }

        return $this;
    }

    public function generateVirtualAccount(): static
    {
        $this->initializeAccount = true;

        return $this;
    }

    public function reference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function customer(string $firstName, ?string $lastName = null, ?string $phoneNumber = null): static
    {
        $this->customerFirstName = $firstName;
        $this->customerLastName = $lastName ?? $this->customerLastName;
        $this->customerPhoneNumber = $phoneNumber ?? $this->customerPhoneNumber;

        return $this;
    }

    public function firstName(string $firstName): static
    {
        $this->customerFirstName = $firstName;

        return $this;
    }

    public function lastName(string $lastName): static
    {
        $this->customerLastName = $lastName;

        return $this;
    }

    public function phoneNumber(string $phoneNumber): static
    {
        $this->customerPhoneNumber = $phoneNumber;

        return $this;
    }

    public function narration(string $narration): static
    {
        $this->narration = $narration;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Attach a custom field in the shape Credo's checkout displays.
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

    public function serviceCode(string $serviceCode): static
    {
        $this->serviceCode = $serviceCode;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $splitConfiguration
     */
    public function splitConfiguration(array $splitConfiguration): static
    {
        $this->splitConfiguration = $splitConfiguration;

        return $this;
    }

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
     * Send the payment to Credo.
     */
    public function send(): InitializeResponse
    {
        return $this->manager->initialize($this);
    }
}
