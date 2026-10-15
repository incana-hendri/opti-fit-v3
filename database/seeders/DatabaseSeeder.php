<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Settings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Monthly price in cents per member count for each package; the top tier means "5 or more" (BR-3.2).
     */
    private const PACKAGES = [
        ['name' => '1× per week', 'sessions' => 1, 'credits' => 4, 'prices' => [1 => 28000, 2 => 42000, 3 => 53200, 4 => 61600, 5 => 67200]],
        ['name' => '2× per week', 'sessions' => 2, 'credits' => 8, 'prices' => [1 => 52000, 2 => 78000, 3 => 99800, 4 => 114400, 5 => 124800]],
        ['name' => '3× per week', 'sessions' => 3, 'credits' => 12, 'prices' => [1 => 72000, 2 => 108000, 3 => 136800, 4 => 158400, 5 => 172800]],
    ];

    /**
     * Production seed: safe to run on an existing database, it only adds what is missing.
     */
    public function run(): void
    {
        if (! Settings::query()->exists()) {
            Settings::query()->create(Settings::DEFAULTS);
        }

        if (! Package::query()->exists()) {
            $this->seedPackages();
        }
    }

    private function seedPackages(): void
    {
        foreach (self::PACKAGES as $order => $data) {
            $package = Package::query()->create([
                'name' => $data['name'],
                'sessions_per_week' => $data['sessions'],
                'credits_per_member' => $data['credits'],
                'sort_order' => $order + 1,
            ]);

            foreach ($data['prices'] as $count => $cents) {
                $package->prices()->create(['member_count' => $count, 'price_cents' => $cents]);
            }
        }
    }
}
