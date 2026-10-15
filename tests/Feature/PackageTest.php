<?php

use App\Models\Membership;
use App\Models\Package;
use Database\Seeders\DatabaseSeeder;

it('prices a family by the tier with the largest member count not above it (BR-3.2)', function (array $tiers, int $members, int $expected) {
    $package = Package::factory()->pricedAt($tiers)->create();

    expect($package->priceFor($members))->toBe($expected);
})->with([
    'five tiers, 1' => [[1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500], 1, 100],
    'five tiers, 2' => [[1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500], 2, 200],
    'five tiers, 5' => [[1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500], 5, 500],
    'five tiers, 9 uses the top tier' => [[1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500], 9, 500],
    'tiers 1 and 3, 1' => [[1 => 100, 3 => 300], 1, 100],
    'tiers 1 and 3, 2 falls back to 1' => [[1 => 100, 3 => 300], 2, 100],
    'tiers 1 and 3, 5' => [[1 => 100, 3 => 300], 5, 300],
    'tiers 1 and 3, 9' => [[1 => 100, 3 => 300], 9, 300],
]);

it('can be deleted only while no membership has used it (BR-3.4)', function () {
    $unused = Package::factory()->create();
    $used = Package::factory()->create();
    Membership::factory()->for($used)->create();

    expect($unused->isDeletable())->toBeTrue()
        ->and($used->isDeletable())->toBeFalse();
});

it('offers only active packages, in order (BR-3.3)', function () {
    Package::query()->delete();
    Package::factory()->create(['name' => 'Second', 'sort_order' => 2]);
    Package::factory()->create(['name' => 'First', 'sort_order' => 1]);
    Package::factory()->inactive()->create(['name' => 'Archived', 'sort_order' => 0]);

    expect(Package::query()->offered()->pluck('name')->all())->toBe(['First', 'Second']);
});

it('seeds the studio packages and tiers once (docs/03 § Seed data)', function () {
    $this->seed(DatabaseSeeder::class);

    $packages = Package::query()->with('prices')->orderBy('sort_order')->get();

    expect($packages)->toHaveCount(3)
        ->and($packages->pluck('name')->all())->toBe(['1× per week', '2× per week', '3× per week'])
        ->and($packages->pluck('credits_per_member')->all())->toBe([4, 8, 12])
        ->and($packages[1]->priceFor(1))->toBe(52000)
        ->and($packages[1]->priceFor(4))->toBe(114400)
        ->and($packages[1]->priceFor(7))->toBe(124800)
        ->and($packages[0]->priceFor(3))->toBe(53200)
        ->and($packages[2]->priceFor(5))->toBe(172800);
});
