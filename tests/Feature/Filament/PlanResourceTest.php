<?php

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Los componentes Livewire no pasan por el middleware del panel,
    // así que se indica manualmente cuál es el panel actual.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// --- Autorización por rol (RF-07, RNF-02) ---

it('shows the plans list to admins and receptionists', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin/plans')
        ->assertSuccessful();
})->with(['admin', 'receptionist']);

it('returns 403 on the plans list for trainers', function () {
    $this->actingAs(User::factory()->trainer()->create())
        ->get('/admin/plans')
        ->assertForbidden();
});

it('returns 403 when a receptionist opens the create or edit page', function () {
    $plan = Plan::factory()->create();
    $receptionist = User::factory()->receptionist()->create();

    $this->actingAs($receptionist)->get('/admin/plans/create')->assertForbidden();
    $this->actingAs($receptionist)->get("/admin/plans/{$plan->getKey()}/edit")->assertForbidden();
});

// --- Creación y reglas de RN-12 (RF-08) ---

it('lets an admin create a plan', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => 'Mensual',
            'duration_days' => 30,
            'price' => 80000,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('plans', [
        'name' => 'Mensual',
        'duration_days' => 30,
        'price' => 80000,
        'is_active' => true,
    ]);
});

it('rejects a duplicate plan name (RN-12)', function () {
    Plan::factory()->create(['name' => 'Mensual']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'Mensual', 'duration_days' => 30, 'price' => 80000])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('rejects a duration below 1 day (RN-12)', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'Diario', 'duration_days' => 0, 'price' => 10000])
        ->call('create')
        ->assertHasFormErrors(['duration_days' => 'min']);
});

it('rejects a price of zero or less (RN-12)', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'Gratis', 'duration_days' => 30, 'price' => 0])
        ->call('create')
        ->assertHasFormErrors(['price' => 'min']);
});

// --- Desactivar en lugar de eliminar (RN-12) ---

it('lets an admin deactivate a plan', function () {
    $plan = Plan::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->fresh()->is_active)->toBeFalse();
});

it('lists plans for an admin', function () {
    $plans = Plan::factory()->count(3)->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPlans::class)
        ->assertCanSeeTableRecords($plans);
});

// --- Scope del modelo (RN-12) ---

it('only returns active plans with the active scope', function () {
    $active = Plan::factory()->create();
    Plan::factory()->inactive()->create();

    expect(Plan::active()->pluck('id')->all())->toBe([$active->id]);
});
