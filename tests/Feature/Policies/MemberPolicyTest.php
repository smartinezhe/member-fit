<?php

declare(strict_types=1);

use App\Models\Member;
use App\Models\User;

it('lets admins do everything except force delete', function () {
    $admin = User::factory()->admin()->create();
    $member = Member::factory()->create();

    expect($admin->can('viewAny', Member::class))->toBeTrue()
        ->and($admin->can('view', $member))->toBeTrue()
        ->and($admin->can('create', Member::class))->toBeTrue()
        ->and($admin->can('update', $member))->toBeTrue()
        ->and($admin->can('delete', $member))->toBeTrue()
        ->and($admin->can('restore', $member))->toBeTrue()
        ->and($admin->can('forceDelete', $member))->toBeFalse();
});

it('lets receptionists view, create and update but not delete or restore', function () {
    $receptionist = User::factory()->receptionist()->create();
    $member = Member::factory()->create();

    expect($receptionist->can('viewAny', Member::class))->toBeTrue()
        ->and($receptionist->can('view', $member))->toBeTrue()
        ->and($receptionist->can('create', Member::class))->toBeTrue()
        ->and($receptionist->can('update', $member))->toBeTrue()
        ->and($receptionist->can('delete', $member))->toBeFalse()
        ->and($receptionist->can('restore', $member))->toBeFalse()
        ->and($receptionist->can('forceDelete', $member))->toBeFalse();
});

it('lets trainers only view their assigned members (RN-13)', function () {
    $trainer = User::factory()->trainer()->create();
    $assigned = Member::factory()->withTrainer($trainer)->create();
    $other = Member::factory()->withTrainer()->create();
    $unassigned = Member::factory()->create();

    expect($trainer->can('viewAny', Member::class))->toBeTrue()
        ->and($trainer->can('view', $assigned))->toBeTrue()
        ->and($trainer->can('view', $other))->toBeFalse()
        ->and($trainer->can('view', $unassigned))->toBeFalse()
        ->and($trainer->can('create', Member::class))->toBeFalse()
        ->and($trainer->can('update', $assigned))->toBeFalse()
        ->and($trainer->can('delete', $assigned))->toBeFalse()
        ->and($trainer->can('restore', $assigned))->toBeFalse();
});

it('never lets anyone force delete members (RN-11)', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $member = Member::factory()->create();

    expect($user->can('forceDelete', $member))->toBeFalse();
})->with(['admin', 'receptionist', 'trainer']);

it('scopes the member list by role with visibleTo (RN-13)', function () {
    $trainer = User::factory()->trainer()->create();
    $assigned = Member::factory()->withTrainer($trainer)->create();
    Member::factory()->withTrainer()->create();
    Member::factory()->create();

    $admin = User::factory()->admin()->create();
    $receptionist = User::factory()->receptionist()->create();

    expect(Member::query()->visibleTo($trainer)->pluck('id')->all())->toBe([$assigned->id])
        ->and(Member::query()->visibleTo($admin)->count())->toBe(3)
        ->and(Member::query()->visibleTo($receptionist)->count())->toBe(3);
});
