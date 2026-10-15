<?php

use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use App\Support\CreditLedger;
use Database\Seeders\DemoSeeder;

it('seeds an admin and three households in different membership states', function () {
    $this->seed(DemoSeeder::class);

    $smiths = User::query()->where('email', 'smith@optifit.test')->firstOrFail()->household;

    expect(User::query()->where('email', 'admin@optifit.test')->exists())->toBeTrue()
        ->and(Household::query()->count())->toBe(3)
        ->and(Membership::query()->where('status', 'requested')->count())->toBe(1)
        ->and(CreditLedger::balance($smiths))->toBe(25);
});

it('can be run twice without duplicating anything', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Household::query()->count())->toBe(3);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
});
