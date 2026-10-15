<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagePrice>
 */
class PackagePriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'member_count' => 1,
            'price_cents' => 52000,
        ];
    }
}
