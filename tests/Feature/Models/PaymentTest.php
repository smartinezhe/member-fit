<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

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

it('only counts payments of non cancelled memberships as income (RN-09)', function () {
    $counted = Payment::factory()->create(['amount' => 80000]);
    $cancelled = Payment::factory()->create([
        'membership_id' => Membership::factory()->cancelled(),
        'amount' => 50000,
    ]);

    $ids = Payment::query()->countedAsIncome()->pluck('id')->all();

    expect($ids)->toBe([$counted->id])
        ->and($ids)->not->toContain($cancelled->id)
        ->and((float) Payment::query()->countedAsIncome()->sum('amount'))->toBe(80000.0);
});

it('filters payments by an inclusive date range (RN-09)', function () {
    $dayBefore = Payment::factory()->create(['paid_at' => '2026-10-31 23:59:59']);
    $firstSecond = Payment::factory()->create(['paid_at' => '2026-11-01 00:00:00']);
    $lastSecond = Payment::factory()->create(['paid_at' => '2026-11-30 23:59:59']);
    $dayAfter = Payment::factory()->create(['paid_at' => '2026-12-01 00:00:00']);

    $ids = Payment::query()
        ->paidBetween(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$firstSecond->id, $lastSecond->id])
        ->and($ids)->not->toContain($dayBefore->id, $dayAfter->id);
});

it('leaves the date range open on the side without a limit (RN-09)', function () {
    $october = Payment::factory()->create(['paid_at' => '2026-10-15 10:00:00']);
    $november = Payment::factory()->create(['paid_at' => '2026-11-15 10:00:00']);

    expect(Payment::query()->paidBetween(null, null)->count())->toBe(2)
        ->and(Payment::query()->paidBetween(Carbon::parse('2026-11-01'), null)->pluck('id')->all())->toBe([$november->id])
        ->and(Payment::query()->paidBetween(null, Carbon::parse('2026-10-31'))->pluck('id')->all())->toBe([$october->id]);
});
