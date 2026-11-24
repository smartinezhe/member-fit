<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Filament\Resources\Members\Pages\ListMembers;
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

/**
 * Crea un socio con una membresía que vence dentro de $days días desde hoy
 * (negativo = ya vencida). Usa las fechas reales de hoy: el umbral N es 7.
 */
function memberEndingIn(int $days): Member
{
    $member = Member::factory()->create();
    $endsOn = today()->addDays($days);

    Membership::factory()->for($member)->create([
        'starts_on' => $endsOn->copy()->subDays(29)->toDateString(),
        'ends_on' => $endsOn->toDateString(),
    ]);

    return $member;
}

// --- Acotación por rol en la lista (RN-13, RF-23) ---

it('lists every member for admins and receptionists', function (string $state) {
    $members = Member::factory()->count(3)->create();
    $this->actingAs(User::factory()->{$state}()->create());

    Livewire::test(ListMembers::class)
        ->assertCanSeeTableRecords($members);
})->with(['admin', 'receptionist']);

it('lists only the assigned members for a trainer (RN-13)', function () {
    $trainer = User::factory()->trainer()->create();
    $assigned = Member::factory()->withTrainer($trainer)->create();
    $other = Member::factory()->withTrainer()->create();
    $unassigned = Member::factory()->create();
    $this->actingAs($trainer);

    Livewire::test(ListMembers::class)
        ->assertCanSeeTableRecords([$assigned])
        ->assertCanNotSeeTableRecords([$other, $unassigned]);
});

// --- Búsqueda (RF-11) ---

it('searches by name, last name, full name, document and phone (RF-11)', function () {
    $ana = Member::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Gómez',
        'document_number' => '1111111111',
        'phone' => '3001112233',
    ]);
    $luis = Member::factory()->create([
        'first_name' => 'Luis',
        'last_name' => 'Pérez',
        'document_number' => '2222222222',
        'phone' => '3204445566',
    ]);
    $this->actingAs(User::factory()->receptionist()->create());

    foreach (['Ana', 'Gómez', 'Ana Gómez', '1111111111', '3001112233'] as $term) {
        Livewire::test(ListMembers::class)
            ->searchTable($term)
            ->assertCanSeeTableRecords([$ana])
            ->assertCanNotSeeTableRecords([$luis]);
    }
});

// --- Filtros (RF-11, RN-05) ---

it('filters by membership status with real data (RN-05)', function () {
    $valid = memberEndingIn(30);
    $expiringSoon = memberEndingIn(3);
    $expired = memberEndingIn(-1);
    $none = Member::factory()->create();
    $all = [$valid, $expiringSoon, $expired, $none];

    $this->actingAs(User::factory()->admin()->create());

    $cases = [
        [MembershipStatus::Valid, $valid],
        [MembershipStatus::ExpiringSoon, $expiringSoon],
        [MembershipStatus::Expired, $expired],
        [MembershipStatus::NoMembership, $none],
    ];

    foreach ($cases as [$status, $expected]) {
        $others = array_filter($all, fn (Member $member) => $member->isNot($expected));

        // filterTable envuelve el valor en ['value' => ...] por sí solo.
        Livewire::test(ListMembers::class)
            ->filterTable('status', $status)
            ->assertCanSeeTableRecords([$expected])
            ->assertCanNotSeeTableRecords($others);
    }
});

it('filters by trainer', function () {
    $trainer = User::factory()->trainer()->create();
    $assigned = Member::factory()->withTrainer($trainer)->create();
    $other = Member::factory()->withTrainer()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListMembers::class)
        ->filterTable('trainer_id', $trainer->id)
        ->assertCanSeeTableRecords([$assigned])
        ->assertCanNotSeeTableRecords([$other]);
});

// --- Eliminar y restaurar solo para el administrador (RN-11) ---

it('lets an admin soft delete a member from the table', function () {
    $member = Member::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListMembers::class)
        ->assertTableActionVisible('delete', $member)
        ->callTableAction('delete', $member);

    $this->assertSoftDeleted('members', ['id' => $member->id]);
});

it('lets an admin restore a soft deleted member', function () {
    $member = Member::factory()->create();
    $member->delete();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListMembers::class)
        ->filterTable('trashed', false)
        ->assertTableActionVisible('restore', $member)
        ->callTableAction('restore', $member);

    expect($member->fresh()->trashed())->toBeFalse();
});

it('hides delete from receptionists but keeps edit and view', function () {
    $member = Member::factory()->create();
    $this->actingAs(User::factory()->receptionist()->create());

    Livewire::test(ListMembers::class)
        ->assertTableActionHidden('delete', $member)
        ->assertTableActionVisible('edit', $member)
        ->assertTableActionVisible('view', $member);
});

it('shows trainers only the view action', function () {
    $trainer = User::factory()->trainer()->create();
    $member = Member::factory()->withTrainer($trainer)->create();
    $this->actingAs($trainer);

    Livewire::test(ListMembers::class)
        ->assertTableActionVisible('view', $member)
        ->assertTableActionHidden('edit', $member)
        ->assertTableActionHidden('delete', $member);
});
