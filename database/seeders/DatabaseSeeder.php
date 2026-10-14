<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production seed: safe to run once on an empty database.
     */
    public function run(): void
    {
        if (! Settings::query()->exists()) {
            Settings::query()->create(Settings::DEFAULTS);
        }
    }
}
