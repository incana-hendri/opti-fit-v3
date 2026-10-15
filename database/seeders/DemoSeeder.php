<?php

namespace Database\Seeders;

use App\Actions\Bookings\PlaceBooking;
use App\Actions\Sessions\GenerateSessions;
use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Enums\Gender;
use App\Enums\MembershipStatus;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Location;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Package;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local demo data: an admin, three households in different membership states, two weeks of sessions and some
 * bookings (docs/03 § Seed data). Run with `sail artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@optifit.test';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder never runs in production.');
        }

        if (User::query()->where('email', self::ADMIN_EMAIL)->exists()) {
            $this->command->info('Demo data already exists, nothing to do.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        User::factory()->admin()->create([
            'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => self::ADMIN_EMAIL,
        ]);

        DB::transaction(function () {
            $this->smiths();
            $this->joneses();
            $this->bothas();
            $this->timetable();
        });

        $this->command->info('Demo data created. Log in with any *@optifit.test address and the password "password".');
    }

    // Active 2× per week since last month, with this month's grant, adjustments and last month's history.
    private function smiths(): void
    {
        $household = $this->household('Anna', 'Smith', 'smith@optifit.test', [
            ['Anna', 'Smith', '1986-03-12', Gender::Female, true],
            ['Mia', 'Smith', '2015-08-19', Gender::Female, false],
            ['Liam', 'Smith', '2018-02-03', Gender::Male, false],
            ['Johan', 'Smith', '1984-11-30', Gender::Male, false],
        ]);

        $previous = Period::previous();
        $current = Period::current();
        $membership = $this->membership($household, '2× per week', ['Anna', 'Mia', 'Liam'], $previous);

        // Last month's rows are dated last month and its expiry the morning after, as the monthly job would have written them.
        Carbon::setTestNow(Period::firstDay($previous)->setTime(0, 5));
        $this->grant($household, $membership, $previous);
        Carbon::setTestNow(Period::firstDay($previous)->addDays(9)->setTime(18, 30));
        CreditLedger::write($household, $previous, CreditEntryType::Adjustment, -3, 'Adjustment: Used at a school event');
        Carbon::setTestNow(Period::firstDay($current)->setTime(0, 5));
        CreditLedger::write($household, $previous, CreditEntryType::Expiry, -(24 - 3), 'Unused '.Period::label($previous).' credits expired', key: "expiry:{$household->id}:{$previous}");
        $this->grant($household, $membership, $current);
        Carbon::setTestNow();

        CreditLedger::write($household, Period::current(), CreditEntryType::Adjustment, 2, 'Adjustment: Make-up for a cancelled class');
        CreditLedger::write($household, Period::current(), CreditEntryType::Adjustment, -1, 'Adjustment: Correction');
    }

    // A first request that is waiting for the admin.
    private function joneses(): void
    {
        $household = $this->household('Ben', 'Jones', 'jones@optifit.test', [
            ['Ben', 'Jones', '1990-06-21', Gender::Male, true],
            ['Sarah', 'Jones', '1991-09-02', Gender::Female, false],
            ['Noah', 'Jones', '2016-04-14', Gender::Male, false],
        ]);

        $request = $this->membership($household, '1× per week', ['Ben', 'Noah'], null);
        $request->forceFill([
            'status' => MembershipStatus::Requested, 'approved_at' => null, 'starts_period' => null,
            'customer_note' => 'We would love early mornings if possible.',
        ])->save();
    }

    // A customer who cancelled: the membership runs to the end of this month.
    private function bothas(): void
    {
        $household = $this->household('Carla', 'Botha', 'botha@optifit.test', [
            ['Carla', 'Botha', '1988-01-25', Gender::Female, true],
            ['Emma', 'Botha', '2014-12-08', Gender::Female, false],
            ['Pieter', 'Botha', '1987-07-17', Gender::Male, false],
        ]);

        $membership = $this->membership($household, '1× per week', ['Carla', 'Emma'], Period::previous());
        $membership->update(['ends_period' => Period::current()]);
        $this->grant($household, $membership, Period::current());
    }

    /**
     * Sessions from a week ago to two weeks ahead, then bookings that cover every state the screens show: next up, an
     * everyone-booked class, a full class, a guest, a class below its minimum today, and past attendance.
     */
    private function timetable(): void
    {
        app(GenerateSessions::class)->handle(ScheduleTemplate::query()->where('is_active', true)->get(), today()->subWeek(), today()->addDays(14));

        $today = $this->sessionToday();
        $upcoming = ClassSession::query()->where('starts_at', '>', now()->addHours(9))->orderBy('starts_at')->get();
        $past = ClassSession::query()->where('starts_at', '<', now())->where('starts_at', '>=', Period::firstDay(Period::current()))->orderByDesc('starts_at')->get();

        $smiths = User::query()->where('email', 'smith@optifit.test')->firstOrFail();
        $botha = User::query()->where('email', 'botha@optifit.test')->firstOrFail();

        $this->book($smiths, $upcoming[0], ['Anna', 'Mia']);
        $this->book($smiths, $upcoming[1], ['Anna', 'Mia', 'Liam']);
        $this->book($smiths, $upcoming[3], ['Mia', 'Liam']);
        $this->book($botha, $today, ['Carla']);

        $upcoming[0]->guestEntries()->create(['name' => 'Trial: Pieter', 'participant_count' => 1, 'note' => 'Friend of the Smiths', 'created_by' => $this->admin()->id]);
        $upcoming[2]->update(['capacity' => 2]);
        $upcoming[2]->guestEntries()->create(['name' => 'Holiday group', 'participant_count' => 2, 'created_by' => $this->admin()->id]);

        // This month's past classes: one attended, one no-show (the admin can return that credit) and one late cancellation.
        $attendance = [['Anna', BookingStatus::Attended, false], ['Mia', BookingStatus::NoShow, false], ['Liam', BookingStatus::Cancelled, true]];

        foreach ($past->values()->take(count($attendance)) as $index => $session) {
            [$name, $status, $late] = $attendance[$index];
            $this->book($smiths, $session, [$name], $status, $late);
        }
    }

    /**
     * A class later today, so the dashboard, /book and the below-minimum email (A5) have something to show.
     */
    private function sessionToday(): ClassSession
    {
        $startsAt = now()->addHour()->startOfHour();

        return ClassSession::factory()->create([
            'location_id' => Location::query()->firstOrFail()->id,
            'title' => 'Evening Functional Fitness',
            'starts_at' => $startsAt->isToday() ? $startsAt : today()->setTime(23, 0),
            'min_participants' => 3,
        ]);
    }

    /**
     * Books covered members like a customer would, minus the emails: the booking and its ledger entry come from PlaceBooking.
     *
     * @param  list<string>  $firstNames
     */
    private function book(User $user, ClassSession $session, array $firstNames, ?BookingStatus $status = null, bool $late = false): void
    {
        foreach ($user->household->members()->whereIn('first_name', $firstNames)->get() as $member) {
            $booking = app(PlaceBooking::class)->handle($user->household, $session, $member, $user);

            if ($status !== null) {
                $booking->update([
                    'status' => $status,
                    'late_cancellation' => $late,
                    'cancelled_at' => $status === BookingStatus::Cancelled ? $session->starts_at->copy()->subHours(2) : null,
                    'cancelled_by' => $status === BookingStatus::Cancelled ? $user->id : null,
                ]);
            }
        }
    }

    private function admin(): User
    {
        return User::query()->where('email', self::ADMIN_EMAIL)->firstOrFail();
    }

    /**
     * @param  list<array{string, string, string, Gender, bool}>  $members  first name, last name, date of birth, gender, is the account holder
     */
    private function household(string $firstName, string $lastName, string $email, array $members): Household
    {
        $user = User::factory()->create(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email]);
        $household = Household::factory()->for($user)->create();

        foreach ($members as [$first, $last, $dateOfBirth, $gender, $isAccountHolder]) {
            Member::factory()->for($household)->create([
                'first_name' => $first, 'last_name' => $last, 'date_of_birth' => $dateOfBirth,
                'gender' => $gender, 'is_account_holder' => $isAccountHolder,
            ]);
        }

        return $household;
    }

    /**
     * @param  list<string>  $covered  first names of the covered members
     */
    private function membership(Household $household, string $packageName, array $covered, ?string $startsPeriod): Membership
    {
        $package = Package::query()->where('name', $packageName)->firstOrFail();
        $members = $household->members()->whereIn('first_name', $covered)->get();

        $membership = Membership::factory()->for($household)->for($package)->active($startsPeriod)->create([
            'monthly_credits' => $package->credits_per_member * $members->count(),
            'monthly_price_cents' => $package->priceFor($members->count()),
        ]);
        $membership->members()->sync($members->pluck('id'));

        return $membership;
    }

    private function grant(Household $household, Membership $membership, string $period): void
    {
        CreditLedger::write(
            $household,
            $period,
            CreditEntryType::Grant,
            $membership->monthly_credits,
            $membership->grantDescription($period),
            $membership,
            key: "grant:{$membership->id}:{$period}",
        );
    }
}
