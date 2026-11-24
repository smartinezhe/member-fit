<?php

declare(strict_types=1);

use App\Actions\RenewMembership;
use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

// Fecha fija en todas las pruebas: hoy = 24/11/2026 10:00 (America/Bogota).
beforeEach(function () {
    $this->travelTo('2026-11-24 10:00:00');
});

/**
 * Ejecuta la Action con valores por defecto razonables.
 *
 * @param  array<string, mixed>  $overrides  Claves: plan, method, amount.
 */
function renewFor(Member $member, array $overrides = []): Membership
{
    return app(RenewMembership::class)->handle(
        $member,
        $overrides['plan'] ?? Plan::factory()->create(),
        User::factory()->receptionist()->create(),
        $overrides['method'] ?? PaymentMethod::Cash,
        $overrides['amount'] ?? null,
    );
}

/** Crea una membresía previa del socio con fechas dadas. */
function previousMembership(Member $member, string $startsOn, string $endsOn, bool $cancelled = false): Membership
{
    $factory = Membership::factory()->for($member);

    if ($cancelled) {
        $factory = $factory->cancelled();
    }

    return $factory->create(['starts_on' => $startsOn, 'ends_on' => $endsOn]);
}

// --- Fechas (RN-02) ---

it('starts a first membership today and ends it after the plan duration (RN-02)', function () {
    $member = Member::factory()->create();

    $membership = renewFor($member);

    expect($membership->starts_on->toDateString())->toBe('2026-11-24')
        // 30 días inclusivos: del 24/11 al 23/12.
        ->and($membership->ends_on->toDateString())->toBe('2026-12-23');
});

it('makes a one-day plan start and end on the same day (RN-02)', function () {
    $member = Member::factory()->create();

    $membership = renewFor($member, ['plan' => Plan::factory()->create(['duration_days' => 1])]);

    expect($membership->starts_on->toDateString())->toBe('2026-11-24')
        ->and($membership->ends_on->toDateString())->toBe('2026-11-24');
});

it('chains an early renewal right after the current one (RN-02)', function () {
    $member = Member::factory()->create();
    previousMembership($member, '2026-11-11', '2026-12-10');

    $membership = renewFor($member);

    expect($membership->starts_on->toDateString())->toBe('2026-12-11')
        ->and($membership->ends_on->toDateString())->toBe('2027-01-09');
});

it('starts tomorrow when the current membership ends today (RN-02)', function () {
    $member = Member::factory()->create();
    previousMembership($member, '2026-10-26', '2026-11-24');

    $membership = renewFor($member);

    expect($membership->starts_on->toDateString())->toBe('2026-11-25');
});

it('starts today when the last membership already expired (RN-02)', function () {
    $member = Member::factory()->create();
    previousMembership($member, '2026-10-12', '2026-11-10');

    $membership = renewFor($member);

    expect($membership->starts_on->toDateString())->toBe('2026-11-24');
});

it('ignores cancelled memberships when chaining dates (RN-02, RN-08)', function () {
    $member = Member::factory()->create();
    previousMembership($member, '2026-10-12', '2026-11-10');
    // Una anulada que vence lejos no debe empujar el inicio.
    previousMembership($member, '2026-12-01', '2026-12-30', cancelled: true);

    $membership = renewFor($member);

    expect($membership->starts_on->toDateString())->toBe('2026-11-24');
});

// --- Pago (RN-03, RN-04) ---

it('creates the payment with the plan price, the method and the registration time (RN-03, RN-04)', function () {
    $member = Member::factory()->create();
    $plan = Plan::factory()->create(['price' => 90000]);

    $membership = renewFor($member, ['plan' => $plan, 'method' => PaymentMethod::Transfer]);

    expect($membership->payment)->toBeInstanceOf(Payment::class)
        ->and($membership->payment->amount)->toBe('90000.00')
        ->and($membership->payment->method)->toBe(PaymentMethod::Transfer)
        ->and($membership->payment->paid_at->toDateTimeString())->toBe('2026-11-24 10:00:00')
        ->and($membership->member_id)->toBe($member->id)
        ->and($membership->plan_id)->toBe($plan->id);
});

it('lets the amount differ from the plan price and keeps it after a price change (RN-03)', function () {
    $member = Member::factory()->create();
    $plan = Plan::factory()->create(['price' => 80000]);

    $membership = renewFor($member, ['plan' => $plan, 'amount' => 70000]);
    $plan->update(['price' => 120000]);

    expect($membership->payment->fresh()->amount)->toBe('70000.00');
});

it('rejects an amount of zero or less and creates nothing (RN-03)', function (int $amount) {
    $member = Member::factory()->create();

    expect(fn () => renewFor($member, ['amount' => $amount]))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('payments', 0);
})->with([0, -5000]);

// --- Plan inactivo (RN-12) ---

it('rejects an inactive plan and creates nothing (RN-12)', function () {
    $member = Member::factory()->create();
    $plan = Plan::factory()->inactive()->create();

    expect(fn () => renewFor($member, ['plan' => $plan]))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('payments', 0);
});

// --- Atomicidad (RNF-03) ---

it('rolls back the membership when the payment cannot be saved (RNF-03)', function () {
    $member = Member::factory()->create();

    // Un valor que excede decimal(12,2): el pago falla DESPUÉS de crear la
    // membresía, que debe deshacerse. Supone MySQL en modo estricto (el de Laravel).
    expect(fn () => renewFor($member, ['amount' => 10000000000000]))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('payments', 0);
});

// --- Socios eliminados ---

it('does not renew a soft deleted member', function () {
    $member = Member::factory()->create();
    $member->delete();

    expect(fn () => renewFor($member))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseCount('memberships', 0);
});
