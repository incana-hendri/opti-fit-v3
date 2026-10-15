<?php

namespace Database\Seeders;

use App\Enums\CreditEntryType;
use App\Enums\Gender;
use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Package;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local demo data: an admin and three households in different membership states (docs/03 § Seed data).
 * Run with `sail artisan db:seed --class=DemoSeeder`. Sessions and bookings are added in Milestone 3.
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
