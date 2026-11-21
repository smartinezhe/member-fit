<?php

use App\Models\Plan;
use App\Models\User;

it('lets admins view, create and update plans', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create();

    expect($admin->can('viewAny', Plan::class))->toBeTrue()
        ->and($admin->can('view', $plan))->toBeTrue()
        ->and($admin->can('create', Plan::class))->toBeTrue()
        ->and($admin->can('update', $plan))->toBeTrue();
});

it('lets receptionists only view plans', function () {
    $receptionist = User::factory()->receptionist()->create();
    $plan = Plan::factory()->create();

    expect($receptionist->can('viewAny', Plan::class))->toBeTrue()
        ->and($receptionist->can('view', $plan))->toBeTrue()
        ->and($receptionist->can('create', Plan::class))->toBeFalse()
        ->and($receptionist->can('update', $plan))->toBeFalse();
});

it('gives trainers no access to plans', function () {
    $trainer = User::factory()->trainer()->create();
    $plan = Plan::factory()->create();

    expect($trainer->can('viewAny', Plan::class))->toBeFalse()
        ->and($trainer->can('view', $plan))->toBeFalse()
        ->and($trainer->can('create', Plan::class))->toBeFalse()
        ->and($trainer->can('update', $plan))->toBeFalse();
});

it('never lets anyone delete or restore plans (RN-12)', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $plan = Plan::factory()->create();

    expect($user->can('delete', $plan))->toBeFalse()
        ->and($user->can('restore', $plan))->toBeFalse()
        ->and($user->can('forceDelete', $plan))->toBeFalse();
})->with(['admin', 'receptionist', 'trainer']);
