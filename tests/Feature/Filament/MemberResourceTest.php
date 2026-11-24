<?php

declare(strict_types=1);

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\Member;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Los componentes Livewire no pasan por el middleware del panel,
    // así que se indica manualmente cuál es el panel actual.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Datos mínimos válidos del formulario de socios.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function memberFormData(array $overrides = []): array
{
    return array_merge([
        'document_type' => 'CC',
        'document_number' => '1234567890',
        'first_name' => 'Ana',
        'last_name' => 'Gómez',
        'phone' => '3001234567',
    ], $overrides);
}

// --- Autorización por rol (RF-07, RNF-02) ---

it('shows the members list to every role', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin/members')
        ->assertSuccessful();
})->with(['admin', 'receptionist', 'trainer']);

it('lets admins and receptionists open the create page', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin/members/create')
        ->assertSuccessful();
})->with(['admin', 'receptionist']);

it('returns 403 on the create page for trainers', function () {
    $this->actingAs(User::factory()->trainer()->create())
        ->get('/admin/members/create')
        ->assertForbidden();
});

it('lets admins and receptionists open the edit page', function (string $state) {
    $member = Member::factory()->create();

    $this->actingAs(User::factory()->{$state}()->create())
        ->get("/admin/members/{$member->getKey()}/edit")
        ->assertSuccessful();
})->with(['admin', 'receptionist']);

it('returns 403 on the edit page for trainers, even for their own members', function () {
    $trainer = User::factory()->trainer()->create();
    $member = Member::factory()->withTrainer($trainer)->create();

    $this->actingAs($trainer)
        ->get("/admin/members/{$member->getKey()}/edit")
        ->assertForbidden();
});

it('lets a trainer open the profile of an assigned member only (RN-13)', function () {
    $trainer = User::factory()->trainer()->create();
    $assigned = Member::factory()->withTrainer($trainer)->create();
    $other = Member::factory()->withTrainer()->create();
    $unassigned = Member::factory()->create();

    $this->actingAs($trainer);

    $this->get("/admin/members/{$assigned->getKey()}")->assertSuccessful();
    $this->get("/admin/members/{$other->getKey()}")->assertForbidden();
    $this->get("/admin/members/{$unassigned->getKey()}")->assertForbidden();
});

it('lets an admin open the profile of a soft deleted member', function () {
    $member = Member::factory()->create();
    $member->delete();

    $this->actingAs(User::factory()->admin()->create())
        ->get("/admin/members/{$member->getKey()}")
        ->assertSuccessful();
});

// --- Creación y reglas de RN-11 / RN-13 (RF-09, RF-10) ---

it('lets a receptionist create a member and stores the phone with digits only (RN-11)', function () {
    $this->actingAs(User::factory()->receptionist()->create());

    Livewire::test(CreateMember::class)
        ->fillForm(memberFormData(['phone' => '+57 (300) 123-4567']))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('members', [
        'document_type' => 'CC',
        'document_number' => '1234567890',
        'phone' => '573001234567',
    ]);
});

it('rejects a duplicate document type and number (RN-11)', function () {
    Member::factory()->create(['document_number' => '1234567890']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMember::class)
        ->fillForm(memberFormData())
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

it('rejects the document of a soft deleted member (RN-11)', function () {
    $member = Member::factory()->create(['document_number' => '1234567890']);
    $member->delete();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMember::class)
        ->fillForm(memberFormData())
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

it('accepts the same number with a different document type', function () {
    Member::factory()->create(['document_number' => '1234567890']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMember::class)
        ->fillForm(memberFormData(['document_type' => 'PA']))
        ->call('create')
        ->assertHasNoFormErrors();
});

it('rejects a phone with letters', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMember::class)
        ->fillForm(memberFormData(['phone' => 'abc']))
        ->call('create')
        ->assertHasFormErrors(['phone' => 'regex']);
});

it('accepts an active trainer as assigned trainer (RN-13)', function () {
    $trainer = User::factory()->trainer()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMember::class)
        ->fillForm(memberFormData(['trainer_id' => $trainer->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('members', [
        'document_number' => '1234567890',
        'trainer_id' => $trainer->id,
    ]);
});

it('rejects an assigned trainer that is not an active trainer (RN-13)', function () {
    $this->actingAs(User::factory()->admin()->create());

    $invalid = [
        User::factory()->receptionist()->create(),
        User::factory()->trainer()->create(['is_active' => false]),
    ];

    foreach ($invalid as $user) {
        Livewire::test(CreateMember::class)
            ->fillForm(memberFormData(['trainer_id' => $user->id]))
            ->call('create')
            ->assertHasFormErrors(['trainer_id']);
    }

    $this->assertDatabaseCount('members', 0);
});

// --- Edición (RF-09) ---

it('lets a receptionist edit a member without tripping the unique rule on its own document', function () {
    $member = Member::factory()->create();
    $this->actingAs(User::factory()->receptionist()->create());

    Livewire::test(EditMember::class, ['record' => $member->getKey()])
        ->fillForm(['first_name' => 'Nuevo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->fresh()->first_name)->toBe('Nuevo');
});
