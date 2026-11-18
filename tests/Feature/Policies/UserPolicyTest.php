<?php

use App\Models\User;

it('lets only admins view, create and update users', function (string $state, bool $allowed) {
    $actor = User::factory()->{$state}()->create();
    $target = User::factory()->create();

    expect($actor->can('viewAny', User::class))->toBe($allowed)
        ->and($actor->can('view', $target))->toBe($allowed)
        ->and($actor->can('create', User::class))->toBe($allowed)
        ->and($actor->can('update', $target))->toBe($allowed);
})->with([
    'admin' => ['admin', true],
    'receptionist' => ['receptionist', false],
    'trainer' => ['trainer', false],
]);

it('never lets anyone delete or restore users', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    expect($admin->can('delete', $target))->toBeFalse()
        ->and($admin->can('restore', $target))->toBeFalse()
        ->and($admin->can('forceDelete', $target))->toBeFalse();
});
