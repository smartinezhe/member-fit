<?php

declare(strict_types=1);

use App\Actions\CancelMembership;
use App\Actions\RenewMembership;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

// Fecha fija en todas las pruebas: hoy = 24/11/2026 10:00 (America/Bogota).
beforeEach(function () {
    $this->travelTo('2026-11-24 10:00:00');
});

/** Crea una membresía de 30 días del socio que vence en la fecha dada. */
function makeMembershipEnding(Member $member, string $endsOn): Membership
{
    return Membership::factory()->for($member)->create([
        'starts_on' => Carbon::parse($endsOn)->subDays(29)->toDateString(),
        'ends_on' => $endsOn,
    ]);
}

// --- Anulación (RN-08) ---

it('cancels the latest membership with its reason (RN-08)', function () {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');

    $result = app(CancelMembership::class)->handle($membership, 'Registro por error');

    expect($result->cancelled_at->toDateTimeString())->toBe('2026-11-24 10:00:00')
        ->and($result->cancellation_reason)->toBe('Registro por error')
        ->and($membership->fresh()->cancelled_at)->not->toBeNull();
});

it('keeps the payment of a cancelled membership (RN-08)', function () {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');
    $payment = Payment::factory()->create(['membership_id' => $membership->id, 'amount' => 80000]);

    app(CancelMembership::class)->handle($membership, 'Registro por error');

    expect($payment->fresh()->amount)->toBe('80000.00');
});

it('leaves the member without membership once the only one is cancelled (RN-05, RN-08)', function () {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');

    app(CancelMembership::class)->handle($membership, 'Registro por error');

    expect($member->fresh()->status)->toBe(MembershipStatus::NoMembership);
});

it('trims the reason before storing it (RN-08)', function () {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');

    $result = app(CancelMembership::class)->handle($membership, '  Pago duplicado  ');

    expect($result->cancellation_reason)->toBe('Pago duplicado');
});

it('accepts a reason of exactly five characters (RN-08)', function () {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');

    $result = app(CancelMembership::class)->handle($membership, 'abcde');

    expect($result->cancelled_at)->not->toBeNull();
});

// --- Motivo inválido: no se anula nada ---

it('rejects a missing, short or blank reason and cancels nothing (RN-08)', function (string $reason) {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');

    expect(fn () => app(CancelMembership::class)->handle($membership, $reason))
        ->toThrow(ValidationException::class);

    expect($membership->fresh()->cancelled_at)->toBeNull();
})->with([
    'empty' => '',
    'four characters' => 'abcd',
    'short once trimmed' => '   ab   ',
]);

it('rejects a reason longer than the column allows (RN-08)', function () {
    $member = Member::factory()->create();
    $membership = makeMembershipEnding($member, '2026-12-10');

    expect(fn () => app(CancelMembership::class)->handle($membership, str_repeat('a', 256)))
        ->toThrow(ValidationException::class);

    expect($membership->fresh()->cancelled_at)->toBeNull();
});

// --- Solo la más reciente (RN-08, opción A) ---

it('rejects cancelling a membership that is not the latest (RN-08)', function () {
    $member = Member::factory()->create();
    $older = makeMembershipEnding($member, '2026-12-10');
    makeMembershipEnding($member, '2027-01-09');

    expect(fn () => app(CancelMembership::class)->handle($older, 'Registro por error'))
        ->toThrow(ValidationException::class);

    expect($older->fresh()->cancelled_at)->toBeNull();
});

it('lets the previous membership be cancelled once the latest is cancelled (RN-08)', function () {
    $member = Member::factory()->create();
    $older = makeMembershipEnding($member, '2026-12-10');
    $latest = makeMembershipEnding($member, '2027-01-09');

    app(CancelMembership::class)->handle($latest, 'Registro por error');
    app(CancelMembership::class)->handle($older, 'Registro por error');

    expect($latest->fresh()->cancelled_at)->not->toBeNull()
        ->and($older->fresh()->cancelled_at)->not->toBeNull();
});

it('rejects cancelling a membership that is already cancelled (RN-08)', function () {
    $member = Member::factory()->create();
    $membership = Membership::factory()->for($member)->cancelled()->create([
        'starts_on' => '2026-11-11',
        'ends_on' => '2026-12-10',
    ]);

    expect(fn () => app(CancelMembership::class)->handle($membership, 'Otro motivo'))
        ->toThrow(ValidationException::class);
});

// --- Integración con la renovación (RN-02, RN-08) ---

it('bases the next renewal on the previous membership after cancelling the latest (RN-02, RN-08)', function () {
    $member = Member::factory()->create();
    makeMembershipEnding($member, '2026-12-10');
    $latest = makeMembershipEnding($member, '2027-01-09');

    app(CancelMembership::class)->handle($latest, 'Registro por error');

    $renewal = app(RenewMembership::class)->handle(
        $member,
        Plan::factory()->create(),
        User::factory()->receptionist()->create(),
        PaymentMethod::Cash,
    );

    expect($renewal->starts_on->toDateString())->toBe('2026-12-11');
});
