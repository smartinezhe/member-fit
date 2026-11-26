<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Los componentes Livewire no pasan por el middleware del panel,
    // así que se indica manualmente cuál es el panel actual.
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Fecha fija: hoy = 24/11/2026.
    $this->travelTo('2026-11-24 10:00:00');
});

/** Monta el gestor de membresías en la ficha de un socio. */
function membershipsManagerFor(Member $member)
{
    return Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ]);
}

// --- Registro desde la ficha (RF-13) ---

it('registers a membership and its payment from the profile (RF-13)', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $this->actingAs($user);
    $member = Member::factory()->create();
    $plan = Plan::factory()->create(['duration_days' => 30, 'price' => 80000]);

    membershipsManagerFor($member)->callTableAction('registerMembership', null, [
        'plan_id' => $plan->id,
        'amount' => 80000,
        'method' => PaymentMethod::Cash->value,
    ]);

    $membership = Membership::query()->where('member_id', $member->id)->firstOrFail();

    expect($membership->plan_id)->toBe($plan->id)
        ->and($membership->created_by)->toBe($user->id)
        ->and($membership->starts_on->toDateString())->toBe('2026-11-24')
        ->and($membership->ends_on->toDateString())->toBe('2026-12-23')
        ->and($membership->payment->amount)->toBe('80000.00')
        ->and($membership->payment->method)->toBe(PaymentMethod::Cash);
})->with(['admin', 'receptionist']);

it('stores a custom amount and the chosen payment method (RN-03, RN-04)', function () {
    $this->actingAs(User::factory()->receptionist()->create());
    $member = Member::factory()->create();
    $plan = Plan::factory()->create(['price' => 80000]);

    membershipsManagerFor($member)->callTableAction('registerMembership', null, [
        'plan_id' => $plan->id,
        'amount' => 70000,
        'method' => PaymentMethod::Transfer->value,
    ]);

    $payment = Membership::query()->where('member_id', $member->id)->firstOrFail()->payment;

    expect($payment->amount)->toBe('70000.00')
        ->and($payment->method)->toBe(PaymentMethod::Transfer);
});

// --- Rechazos: no se crea nada ---

it('creates nothing when the amount is zero or less (RN-03)', function (int $amount) {
    $this->actingAs(User::factory()->receptionist()->create());
    $member = Member::factory()->create();
    $plan = Plan::factory()->create();

    membershipsManagerFor($member)->callTableAction('registerMembership', null, [
        'plan_id' => $plan->id,
        'amount' => $amount,
        'method' => PaymentMethod::Cash->value,
    ]);

    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('payments', 0);
})->with([0, -5000]);

it('creates nothing for an inactive plan (RN-12)', function () {
    $this->actingAs(User::factory()->receptionist()->create());
    $member = Member::factory()->create();
    $plan = Plan::factory()->inactive()->create();

    membershipsManagerFor($member)->callTableAction('registerMembership', null, [
        'plan_id' => $plan->id,
        'amount' => 80000,
        'method' => PaymentMethod::Cash->value,
    ]);

    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('payments', 0);
});

// --- Autorización ---

it('does not let trainers use the memberships manager at all (RF-23)', function () {
    $this->actingAs(User::factory()->trainer()->create());
    $member = Member::factory()->create();

    membershipsManagerFor($member)->assertForbidden();
});
