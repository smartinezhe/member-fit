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

it('shows the memberships manager to admins and receptionists only (RF-23)', function (string $state, bool $visible) {
    $this->actingAs(User::factory()->{$state}()->create());
    $member = Member::factory()->create();

    expect(MembershipsRelationManager::canViewForRecord($member, ViewMember::class))->toBe($visible);
})->with([
    'admin' => ['admin', true],
    'receptionist' => ['receptionist', true],
    'trainer' => ['trainer', false],
]);

it('lists only the memberships of the member (RF-12)', function () {
    $member = Member::factory()->create();
    $own = Membership::factory()->for($member)->count(2)->create();
    $foreign = Membership::factory()->create();

    $this->actingAs(User::factory()->receptionist()->create());

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->assertCanSeeTableRecords($own)
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('is not read-only on the member profile page', function () {
    $member = Member::factory()->create();
    $this->actingAs(User::factory()->receptionist()->create());

    $component = Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ]);

    expect($component->instance()->isReadOnly())->toBeFalse();
});
