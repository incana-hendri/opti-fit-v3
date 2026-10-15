<?php

use App\Exceptions\SessionException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Location;
use App\Models\Member;
use App\Models\Membership;
use App\Models\ScheduleTemplate;
use App\Support\Cutoff;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
});

it('seeds the venue and the six weekly templates once (docs/03)', function () {
    $location = Location::query()->sole();

    expect($location->name)->toBe('Gene Louw Activity Centre')
        ->and($location->address)->toBe('Tulip Street, Amanda Glen, Durbanville')
        ->and(ScheduleTemplate::query()->count())->toBe(6)
        ->and(ScheduleTemplate::query()->where('duration_minutes', 45)->where('capacity', 15)->where('min_participants', 3)->count())->toBe(6);

    $this->seed(DatabaseSeeder::class);

    expect(Location::query()->count())->toBe(1)
        ->and(ScheduleTemplate::query()->count())->toBe(6);
});

it('works out the cutoff from the session override, else the setting (D2)', function () {
    $session = classSession(['starts_at' => '2026-10-19 05:00']);

    expect(Cutoff::hoursFor($session))->toBe(8)
        ->and($session->cutoffAt()->format('Y-m-d H:i'))->toBe('2026-10-18 21:00');

    $session->update(['cutoff_hours' => 12]);

    expect(Cutoff::hoursFor($session))->toBe(12)
        ->and($session->cutoffAt()->format('Y-m-d H:i'))->toBe('2026-10-18 17:00');
});

it('lets customers book to the end of the shorter of the horizon and the month (BR-7.2)', function () {
    expect(Cutoff::windowEnd()->toDateString())->toBe('2026-10-29');

    $this->travelTo(Carbon::parse('2026-10-25 10:00'));

    expect(Cutoff::windowEnd()->toDateString())->toBe('2026-10-31');
});

it('covers only the active members of the membership in effect (BR-4.10)', function () {
    $user = customer(3);
    activeMembership($user);
    $archived = $user->household->members->first();
    $archived->forceFill(['archived_at' => now()])->save();
    $outsider = Member::factory()->for($user->household)->create();

    $covered = $user->household->coveredMembers();

    expect($covered->pluck('id')->all())->toBe($user->household->members->skip(1)->pluck('id')->all())
        ->and($covered->contains($outsider))->toBeFalse()
        ->and($covered->contains($archived))->toBeFalse();
});

it('covers nobody while the membership is paused, cancelled or only requested (BR-4.10)', function (string $state) {
    $user = customer();
    match ($state) {
        'paused' => Membership::factory()->for($user->household)->paused()->create(),
        'cancelled' => Membership::factory()->for($user->household)->cancelled()->create(),
        'requested' => Membership::factory()->for($user->household)->create(),
    };

    expect($user->household->coveredMembers())->toBeEmpty();
})->with(['paused', 'cancelled', 'requested']);

it('lets a member with bookings be archived but not deleted (BR-2.5)', function () {
    $member = Member::factory()->create();

    expect($member->canBeDeleted())->toBeTrue();

    Booking::factory()->for($member)->create();

    expect($member->fresh()->canBeDeleted())->toBeFalse()
        ->and(Member::query()->withCount(['memberships', 'bookings'])->find($member->id)->canBeDeleted())->toBeFalse();
});

it('cannot archive a location that a future session or an active template uses (BR-9.2)', function () {
    $location = Location::factory()->create();

    ClassSession::factory()->for($location)->create(['starts_at' => '2026-10-17 05:00']);
    expect($location->isArchivable())->toBeFalse()
        ->and(fn () => $location->archive())->toThrow(SessionException::class);

    ClassSession::query()->delete();
    ScheduleTemplate::factory()->for($location)->create();
    expect($location->isArchivable())->toBeFalse();

    ScheduleTemplate::query()->update(['is_active' => false]);
    ClassSession::factory()->for($location)->create(['starts_at' => '2026-10-10 05:00']);
    expect($location->isArchivable())->toBeTrue();

    $location->archive();
    expect($location->fresh()->is_active)->toBeFalse();
});

it('shows the first active location as the venue', function () {
    Location::query()->update(['is_active' => false]);
    $second = Location::factory()->create(['name' => 'Community Hall']);

    expect(Location::venue()->is($second))->toBeTrue();
});
