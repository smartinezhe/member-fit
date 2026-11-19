<?php

use App\Enums\Role;
use App\Models\User;

it('does not let the last active admin be deactivated', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $admin->update(['is_active' => false]))
        ->toThrow(DomainException::class);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('does not let the last active admin lose the admin role', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $admin->update(['role' => Role::Receptionist]))
        ->toThrow(DomainException::class);

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

it('lets an admin be deactivated when another active admin remains', function () {
    $first = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $first->update(['is_active' => false]);

    expect($first->fresh()->is_active)->toBeFalse();
});

it('ignores updates that do not touch role or status', function () {
    $admin = User::factory()->admin()->create();

    $admin->update(['name' => 'Nuevo nombre']);

    expect($admin->fresh()->name)->toBe('Nuevo nombre');
});
