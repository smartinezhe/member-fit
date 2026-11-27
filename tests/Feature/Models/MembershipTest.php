<?php

declare(strict_types=1);

use App\Models\Member;
use App\Models\Membership;
use Illuminate\Database\QueryException;

it('links a membership to its member, plan and creator', function () {
    $membership = Membership::factory()->create();

    expect($membership->member)->toBeInstanceOf(Member::class)
        ->and($membership->plan)->not->toBeNull()
        ->and($membership->creator)->not->toBeNull();
});

it('lists the memberships of a member (RF-12)', function () {
    $member = Member::factory()->create();
    Membership::factory()->count(2)->for($member)->create();

    expect($member->memberships)->toHaveCount(2);
});

it('excludes cancelled memberships with the notCancelled scope (RN-08)', function () {
    $active = Membership::factory()->create();
    Membership::factory()->cancelled()->create();

    $ids = Membership::query()->notCancelled()->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe($active->id);
});

it('rejects an end date before the start date', function () {
    expect(fn () => Membership::factory()->create([
        'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-19',
    ]))->toThrow(QueryException::class);
});

it('reads the expiry alert threshold from config (RN-07)', function () {
    expect(config('memberfit.expiry_alert_days'))->toBe(7);
});

it('only the latest not cancelled membership is cancellable (RN-08)', function () {
    $member = Member::factory()->create();
    $older = Membership::factory()->for($member)->create(['starts_on' => '2026-11-11', 'ends_on' => '2026-12-10']);
    $latest = Membership::factory()->for($member)->create(['starts_on' => '2026-12-11', 'ends_on' => '2027-01-09']);

    expect($latest->isLatestNotCancelled())->toBeTrue()
        ->and($older->isLatestNotCancelled())->toBeFalse();
});

it('is not cancellable once it is already cancelled (RN-08)', function () {
    $membership = Membership::factory()->cancelled()->create();

    expect($membership->isLatestNotCancelled())->toBeFalse();
});

it('makes the previous membership the latest when the newest is cancelled (RN-08)', function () {
    $member = Member::factory()->create();
    $older = Membership::factory()->for($member)->create(['starts_on' => '2026-11-11', 'ends_on' => '2026-12-10']);
    Membership::factory()->for($member)->cancelled()->create(['starts_on' => '2026-12-11', 'ends_on' => '2027-01-09']);

    expect($older->isLatestNotCancelled())->toBeTrue();
});

it('is the latest when it is the only membership of the member (RN-08)', function () {
    $membership = Membership::factory()->create();

    expect($membership->isLatestNotCancelled())->toBeTrue();
});
