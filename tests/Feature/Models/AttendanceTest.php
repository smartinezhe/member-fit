<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Database\QueryException;

it('links an attendance to its member and the user who checked in', function () {
    $attendance = Attendance::factory()->create();

    expect($attendance->member)->toBeInstanceOf(Member::class)
        ->and($attendance->checkedInBy)->not->toBeNull();
});

it('lists the attendances of a member', function () {
    $member = Member::factory()->create();
    Attendance::factory()->for($member)->create(['attended_on' => '2026-11-24']);
    Attendance::factory()->for($member)->create(['attended_on' => '2026-11-25']);

    expect($member->attendances)->toHaveCount(2);
});

it('rejects a second attendance for the same member on the same day (RN-06)', function () {
    $member = Member::factory()->create();
    Attendance::factory()->for($member)->create(['attended_on' => '2026-11-24']);

    expect(fn () => Attendance::factory()->for($member)->create(['attended_on' => '2026-11-24']))
        ->toThrow(QueryException::class);
});

it('allows the same member on different days and different members on the same day (RN-06)', function () {
    $member = Member::factory()->create();
    Attendance::factory()->for($member)->create(['attended_on' => '2026-11-24']);

    $nextDay = Attendance::factory()->for($member)->create(['attended_on' => '2026-11-25']);
    $otherMember = Attendance::factory()->create(['attended_on' => '2026-11-24']);

    expect($nextDay->exists)->toBeTrue()
        ->and($otherMember->exists)->toBeTrue();
});

it('casts the day to a date and the time to a datetime', function () {
    $attendance = Attendance::factory()->create([
        'attended_on' => '2026-11-24',
        'checked_in_at' => '2026-11-24 08:15:00',
    ]);

    $fresh = $attendance->fresh();

    expect($fresh->attended_on->toDateString())->toBe('2026-11-24')
        ->and($fresh->checked_in_at->toDateTimeString())->toBe('2026-11-24 08:15:00');
});
