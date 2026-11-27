<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\User;

it('lets admins view payments (RF-15)', function () {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->create();

    expect($admin->can('viewAny', Payment::class))->toBeTrue()
        ->and($admin->can('view', $payment))->toBeTrue();
});

it('gives receptionists and trainers no access to payments (RF-15)', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $payment = Payment::factory()->create();

    expect($user->can('viewAny', Payment::class))->toBeFalse()
        ->and($user->can('view', $payment))->toBeFalse();
})->with(['receptionist', 'trainer']);

it('never lets anyone create, edit or delete payments (RN-04)', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $payment = Payment::factory()->create();

    expect($user->can('create', Payment::class))->toBeFalse()
        ->and($user->can('update', $payment))->toBeFalse()
        ->and($user->can('delete', $payment))->toBeFalse()
        ->and($user->can('restore', $payment))->toBeFalse()
        ->and($user->can('forceDelete', $payment))->toBeFalse();
})->with(['admin', 'receptionist', 'trainer']);
