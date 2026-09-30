<?php

use ZoweSoft\LaravelCredo\Enums\Channel;
use ZoweSoft\LaravelCredo\Enums\Currency;
use ZoweSoft\LaravelCredo\Enums\FeeBearer;
use ZoweSoft\LaravelCredo\Facades\Credo;

it('builds a minimal payment payload', function () {
    $payload = Credo::payment()
        ->amount(15000)
        ->email('customer@example.com')
        ->toArray();

    expect($payload)->toBe([
        'amount' => 15000,
        'email' => 'customer@example.com',
        'currency' => 'NGN',
        'bearer' => 0,
    ]);
});

it('converts major-unit amounts to kobo', function () {
    $payload = Credo::payment()
        ->amountInMajorUnits(150.5)
        ->email('customer@example.com')
        ->toArray();

    expect($payload['amount'])->toBe(15050);
});

it('includes optional fields only when set', function () {
    $payload = Credo::payment()
        ->amount(100000)
        ->email('student@example.com')
        ->reference('PG-APP-0001')
        ->callbackUrl('https://portal.test/credo/callback')
        ->customer('Ada', 'Obi', '2348012345678')
        ->narration('Application fee')
        ->channels(['card'])
        ->card()
        ->bank()
        ->metadata(['matric' => 'ENG/2024/001'])
        ->customField('Programme', 'programme', 'Computer Science')
        ->toArray();

    expect($payload['reference'])->toBe('PG-APP-0001')
        ->and($payload['callbackUrl'])->toBe('https://portal.test/credo/callback')
        ->and($payload['customerFirstName'])->toBe('Ada')
        ->and($payload['customerLastName'])->toBe('Obi')
        ->and($payload['customerPhoneNumber'])->toBe('2348012345678')
        ->and($payload['narration'])->toBe('Application fee')
        ->and($payload['channels'])->toBe(['CARD', 'BANK'])
        ->and($payload['metadata'])->toBe([
            'matric' => 'ENG/2024/001',
            'customFields' => [
                ['display_name' => 'Programme', 'variable_name' => 'programme', 'value' => 'Computer Science'],
            ],
        ])
        ->and($payload)->not->toHaveKey('serviceCode')
        ->and($payload)->not->toHaveKey('splitConfiguration')
        ->and($payload)->not->toHaveKey('pauseSettlement');
});

it('supports enums for currency, bearer and channels', function () {
    $payload = Credo::payment()
        ->amount(2500)
        ->email('customer@example.com')
        ->currency(Currency::USD)
        ->bearer(FeeBearer::MERCHANT)
        ->channels([Channel::CARD])
        ->toArray();

    expect($payload['currency'])->toBe('USD')
        ->and($payload['bearer'])->toBe(1)
        ->and($payload['channels'])->toBe(['CARD']);
});

it('flips the fee bearer with helper methods', function () {
    $builder = Credo::payment()->amount(100)->email('a@b.com');

    expect($builder->customerBearsFee()->toArray()['bearer'])->toBe(0)
        ->and($builder->merchantBearsFee()->toArray()['bearer'])->toBe(1);
});

it('flags virtual account generation and settlement pausing', function () {
    $payload = Credo::payment()
        ->amount(100)
        ->email('a@b.com')
        ->generateVirtualAccount()
        ->pauseSettlement('2026-03-15')
        ->toArray();

    expect($payload['initializeAccount'])->toBe(1)
        ->and($payload['pauseSettlement'])->toBe(1)
        ->and($payload['pauseSettlementDate'])->toBe('2026-03-15');
});

it('accepts string currency and bearer values', function () {
    $payload = Credo::payment()
        ->amount(100)
        ->email('a@b.com')
        ->currency('USD')
        ->bearer(1)
        ->toArray();

    expect($payload['currency'])->toBe('USD')
        ->and($payload['bearer'])->toBe(1);
});
