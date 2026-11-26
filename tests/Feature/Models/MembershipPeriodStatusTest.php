<?php

declare(strict_types=1);

use App\Enums\MembershipPeriodStatus;
use App\Models\Membership;

// Fecha fija: hoy = 24/11/2026.
it('calculates the status of a membership (RF-12)', function (
    string $startsOn,
    string $endsOn,
    bool $cancelled,
    MembershipPeriodStatus $expected,
) {
    $this->travelTo('2026-11-24 10:00:00');

    $membership = new Membership(['starts_on' => $startsOn, 'ends_on' => $endsOn]);

    if ($cancelled) {
        $membership->cancelled_at = now();
    }

    expect($membership->status)->toBe($expected);
})->with([
    'active, ends today' => ['2026-10-26', '2026-11-24', false, MembershipPeriodStatus::Active],
    'active, starts today' => ['2026-11-24', '2026-12-23', false, MembershipPeriodStatus::Active],
    'upcoming, starts tomorrow' => ['2026-11-25', '2026-12-24', false, MembershipPeriodStatus::Upcoming],
    'expired, ended yesterday' => ['2026-10-25', '2026-11-23', false, MembershipPeriodStatus::Expired],
    'cancelled with current dates' => ['2026-11-01', '2026-11-30', true, MembershipPeriodStatus::Cancelled],
    'cancelled and already expired' => ['2026-09-01', '2026-09-30', true, MembershipPeriodStatus::Cancelled],
]);
