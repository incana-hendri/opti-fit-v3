<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Settings;
use App\Support\Money;
use Illuminate\Contracts\View\View;

// docs/04 § /pricing: every active package priced for family sizes 1 to 5+ (BR-3.2, 3.3, 3.6).
class PricingController
{
    private const LARGEST_SIZE = 5;

    public function __invoke(): View
    {
        $packages = Package::query()->offered()->with('prices')->get()->map(fn (Package $package) => [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'sizes' => $this->sizesFor($package),
        ]);

        return view('pages.pricing', [
            'packages' => $packages,
            'sizes' => range(1, self::LARGEST_SIZE),
            'largestSize' => self::LARGEST_SIZE,
            'cutoffHours' => Settings::current()->default_cutoff_hours,
        ]);
    }

    /**
     * Prices are worked out here so the page's Alpine only picks a ready-made string (BR-3.2 lives in one place).
     *
     * @return array<int, array{credits: string, price: string, perClass: string}>
     */
    private function sizesFor(Package $package): array
    {
        $sizes = [];

        foreach (range(1, self::LARGEST_SIZE) as $size) {
            $credits = $package->credits_per_member * $size;
            $price = $package->priceFor($size);

            $sizes[$size] = [
                'credits' => $credits.($size === self::LARGEST_SIZE ? '+' : ''),
                'price' => Money::format($price),
                'perClass' => '≈ '.Money::format((int) round($price / $credits / 100) * 100).' per class',
            ];
        }

        return $sizes;
    }
}
