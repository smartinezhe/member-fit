<?php

declare(strict_types=1);

use App\Models\Membership;
use App\Models\User;

it('lets admins and receptionists view and register memberships', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $membership = Membership::factory()->create();

    expect($user->can('viewAny', Membership::class))->toBeTrue()
        ->and($user->can('view', $membership))->toBeTrue()
        ->and($user->can('create', Membership::class))->toBeTrue();
})->with(['admin', 'receptionist']);

it('never lets admins or receptionists edit or delete memberships (RN-02, RN-08)', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $membership = Membership::factory()->create();

    expect($user->can('update', $membership))->toBeFalse()
        ->and($user->can('delete', $membership))->toBeFalse()
        ->and($user->can('restore', $membership))->toBeFalse()
        ->and($user->can('forceDelete', $membership))->toBeFalse();
})->with(['admin', 'receptionist']);

it('gives trainers no access to memberships (RF-23)', function () {
    $trainer = User::factory()->trainer()->create();
    $membership = Membership::factory()->create();

    expect($trainer->can('viewAny', Membership::class))->toBeFalse()
        ->and($trainer->can('view', $membership))->toBeFalse()
        ->and($trainer->can('create', Membership::class))->toBeFalse();
});

it('lets only admins cancel memberships (RN-08)', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $membership = Membership::factory()->create();

    expect($user->can('cancel', $membership))->toBe($allowed);
})->with([
    'admin' => ['admin', true],
    'receptionist' => ['receptionist', false],
    'trainer' => ['trainer', false],
]);
