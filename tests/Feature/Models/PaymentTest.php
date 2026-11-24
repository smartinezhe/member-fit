<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Database\QueryException;

it('links a payment to its membership and back (RN-04)', function () {
    $payment = Payment::factory()->create();

    expect($payment->membership)->toBeInstanceOf(Membership::class)
        ->and($payment->membership->payment->is($payment))->toBeTrue();
});

it('casts the method to the PaymentMethod enum and the amount to two decimals', function () {
    $payment = Payment::factory()->create([
        'method' => PaymentMethod::Transfer,
        'amount' => 95000,
    ]);

    $fresh = $payment->fresh();

    expect($fresh->method)->toBe(PaymentMethod::Transfer)
        ->and($fresh->amount)->toBe('95000.00');
});

it('rejects a second payment for the same membership (RN-04)', function () {
    $membership = Membership::factory()->create();
    Payment::factory()->create(['membership_id' => $membership->id]);

    expect(fn () => Payment::factory()->create(['membership_id' => $membership->id]))
        ->toThrow(QueryException::class);
});

it('rejects an amount of zero or less at the database level (RN-03)', function (int $amount) {
    expect(fn () => Payment::factory()->create(['amount' => $amount]))
        ->toThrow(QueryException::class);
})->with([0, -1000]);
