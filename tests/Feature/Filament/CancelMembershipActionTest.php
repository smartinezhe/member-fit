<?php

declare(strict_types=1);

use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Los componentes Livewire no pasan por el middleware del panel,
    // así que se indica manualmente cuál es el panel actual.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** Monta el gestor de membresías en la ficha de un socio. */
function cancelManagerFor(Member $member)
{
    return Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ]);
}

// --- Anulación desde la ficha (RF-14, RN-08) ---

it('lets an admin cancel the latest membership with a reason (RF-14)', function () {
    $this->actingAs(User::factory()->admin()->create());
    $membership = Membership::factory()->create();

    cancelManagerFor($membership->member)->callTableAction('cancelMembership', $membership, [
        'reason' => 'Registro por error',
    ]);

    $fresh = $membership->fresh();

    expect($fresh->cancelled_at)->not->toBeNull()
        ->and($fresh->cancellation_reason)->toBe('Registro por error');
});

it('creates no cancellation when the reason is too short (RN-08)', function () {
    $this->actingAs(User::factory()->admin()->create());
    $membership = Membership::factory()->create();

    cancelManagerFor($membership->member)->callTableAction('cancelMembership', $membership, [
        'reason' => 'abc',
    ]);

    expect($membership->fresh()->cancelled_at)->toBeNull();
});

// --- Visibilidad ---

it('hides the cancel action from receptionists (RN-08)', function () {
    $this->actingAs(User::factory()->receptionist()->create());
    $membership = Membership::factory()->create();

    cancelManagerFor($membership->member)->assertTableActionHidden('cancelMembership', $membership);
});

it('shows the cancel action only on the latest membership (RN-08)', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = Member::factory()->create();
    $older = Membership::factory()->for($member)->create(['starts_on' => '2026-11-11', 'ends_on' => '2026-12-10']);
    $latest = Membership::factory()->for($member)->create(['starts_on' => '2026-12-11', 'ends_on' => '2027-01-09']);

    cancelManagerFor($member)
        ->assertTableActionVisible('cancelMembership', $latest)
        ->assertTableActionHidden('cancelMembership', $older);
});

it('hides the cancel action on an already cancelled membership (RN-08)', function () {
    $this->actingAs(User::factory()->admin()->create());
    $membership = Membership::factory()->cancelled()->create();

    cancelManagerFor($membership->member)->assertTableActionHidden('cancelMembership', $membership);
});
