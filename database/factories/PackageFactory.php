<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /** Monthly price in cents per member count; the top tier means "5 or more" (BR-3.2). */
    private const DEFAULT_TIERS = [1 => 52000, 2 => 78000, 3 => 99800, 4 => 114400, 5 => 124800];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'sessions_per_week' => 2,
            'credits_per_member' => 8,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    // A package always needs the 1-member tier (BR-3.2), so the default state gets standard tiers.
    public function configure(): static
    {
        return $this->afterCreating(fn (Package $package) => $this->replaceTiers($package, self::DEFAULT_TIERS));
    }

    /**
     * Use these tiers instead of the default ones.
     *
     * @param  array<int, int>  $tiers  member count => price in cents
     */
    public function pricedAt(array $tiers): static
    {
        return $this->afterCreating(fn (Package $package) => $this->replaceTiers($package, $tiers));
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * @param  array<int, int>  $tiers
     */
    private function replaceTiers(Package $package, array $tiers): void
    {
        $package->prices()->delete();

        foreach ($tiers as $count => $cents) {
            PackagePrice::factory()->for($package)->create(['member_count' => $count, 'price_cents' => $cents]);
        }

        $package->unsetRelation('prices');
    }
}
