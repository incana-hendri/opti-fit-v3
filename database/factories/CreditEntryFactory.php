<?php

namespace Database\Factories;

use App\Enums\CreditEntryType;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Support\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditEntry>
 */
class CreditEntryFactory extends Factory
{
    /**
     * A grant of 8 credits for the current period. Tests that need the ledger rules go through `CreditLedger`;
     * the factory only seeds rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'period' => Period::current(),
            'type' => CreditEntryType::Grant,
            'amount' => 8,
            'description' => 'Monthly credits',
        ];
    }

    public function forPeriod(string $period): static
    {
        return $this->state(fn (array $attributes) => ['period' => $period]);
    }

    public function ofType(CreditEntryType $type, int $amount): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type, 'amount' => $amount]);
    }
}
