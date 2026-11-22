<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Support\Carbon;

// Fecha fija: hoy = 22/11/2026, umbral N = 7 (por defecto).

it('calculates the status at the border dates (RN-05)', function (?string $endsOn, MembershipStatus $expected) {
    $this->travelTo('2026-11-22 10:00:00');

    $status = MembershipStatus::fromEndsOn($endsOn === null ? null : Carbon::parse($endsOn), 7);

    expect($status)->toBe($expected);
})->with([
    'no membership' => [null, MembershipStatus::NoMembership],
    'ended yesterday' => ['2026-11-21', MembershipStatus::Expired],
    'ends today' => ['2026-11-22', MembershipStatus::ExpiringSoon],
    'ends today + N' => ['2026-11-29', MembershipStatus::ExpiringSoon],
    'ends today + N + 1' => ['2026-11-30', MembershipStatus::Valid],
]);

/**
 * Crea un socio con membresías dadas como [ends_on, anulada?].
 *
 * @param  array<int, array{0: string, 1?: bool}>  $memberships
 */
function memberWith(array $memberships): Member
{
    $member = Member::factory()->create();

    foreach ($memberships as $data) {
        $endsOn = $data[0];
        $cancelled = $data[1] ?? false;

        $factory = Membership::factory()->for($member);

        if ($cancelled) {
            $factory = $factory->cancelled();
        }

        $factory->create([
            'starts_on' => Carbon::parse($endsOn)->subDays(29)->toDateString(),
            'ends_on' => $endsOn,
        ]);
    }

    return $member;
}

it('agrees between the PHP accessor and the SQL scopes (RN-05)', function () {
    $this->travelTo('2026-11-22 10:00:00');

    $cases = [
        [memberWith([]), MembershipStatus::NoMembership],
        [memberWith([['2026-12-30', true]]), MembershipStatus::NoMembership],
        [memberWith([['2026-11-21']]), MembershipStatus::Expired],
        [memberWith([['2026-11-22']]), MembershipStatus::ExpiringSoon],
        [memberWith([['2026-11-29']]), MembershipStatus::ExpiringSoon],
        [memberWith([['2026-11-30']]), MembershipStatus::Valid],
        // Renovación continua: la más reciente manda (RN-02).
        [memberWith([['2026-11-12'], ['2026-12-12']]), MembershipStatus::Valid],
        // Anulada la más reciente, cuenta la anterior.
        [memberWith([['2026-11-21'], ['2026-12-20', true]]), MembershipStatus::Expired],
    ];

    foreach ($cases as [$member, $expected]) {
        // Accessor sin precarga (consulta propia).
        expect($member->fresh()->status)->toBe($expected);

        // Accessor con precarga de latest_ends_on.
        $preloaded = Member::query()->withLatestEndDate()->find($member->id);
        expect($preloaded->status)->toBe($expected);
    }

    // Scopes SQL: cada estado devuelve exactamente sus socios.
    foreach (MembershipStatus::cases() as $status) {
        $expectedIds = collect($cases)
            ->filter(fn (array $case) => $case[1] === $status)
            ->map(fn (array $case) => $case[0]->id)
            ->sort()
            ->values()
            ->all();

        $actualIds = Member::query()->inStatus($status)->pluck('id')->sort()->values()->all();

        expect($actualIds)->toBe($expectedIds);
    }
});
