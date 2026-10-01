<?php

use ZoweSoft\LaravelCredo\Data\Transaction;

function makeTransaction(array $overrides = []): Transaction
{
    return Transaction::fromArray(array_merge([
        'transRef' => 'vs_check',
        'businessRef' => 'ORD-1',
        'transAmount' => 2500.0,
        'debitedAmount' => 2637.5,
        'transFeeAmount' => 137.5,
        'customerId' => 'student@example.com',
        'currencyCode' => 'NGN',
        'status' => 0,
    ], $overrides));
}

it('passes the full checklist for a matching successful transaction', function () {
    $transaction = makeTransaction();

    expect($transaction->matches(2500.00, 'NGN', 'ORD-1'))->toBeTrue();
});

it('passes with only an amount when currency and reference are omitted', function () {
    expect(makeTransaction()->matches(2500.00))->toBeTrue();
});

it('fails the checklist when the status is not successful', function () {
    expect(makeTransaction(['status' => 3])->matches(2500.00, 'NGN', 'ORD-1'))->toBeFalse();
});

it('fails the checklist when the amount differs beyond the tolerance', function () {
    expect(makeTransaction(['transAmount' => 2500.01])->matches(2500.00, 'NGN', 'ORD-1'))->toBeFalse()
        ->and(makeTransaction(['transAmount' => 2400.00])->matches(2500.00, 'NGN', 'ORD-1'))->toBeFalse();
});

it('fails the checklist when the currency differs', function () {
    expect(makeTransaction(['currencyCode' => 'USD'])->matches(2500.00, 'NGN', 'ORD-1'))->toBeFalse();
});

it('compares the currency case-insensitively', function () {
    expect(makeTransaction()->matches(2500.00, 'ngn', 'ORD-1'))->toBeTrue();
});

it('fails the checklist when the business reference differs', function () {
    expect(makeTransaction(['businessRef' => 'ORD-999'])->matches(2500.00, 'NGN', 'ORD-1'))->toBeFalse();
});

it('skips the currency and reference checks when null is passed', function () {
    $transaction = makeTransaction(['currencyCode' => 'USD', 'businessRef' => 'OTHER']);

    expect($transaction->matches(2500.00, null, null))->toBeTrue();
});
