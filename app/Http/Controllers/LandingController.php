<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Location;
use App\Models\Package;
use App\Support\Money;
use Illuminate\Contracts\View\View;

// docs/04 § Landing: hero, how it works, packages teaser, this week's timetable (the next 7 days), venue card.
class LandingController
{
    private const TIMETABLE_DAYS = 7;

    public function __invoke(): View
    {
        return view('pages.landing', [
            'packages' => $this->packageTeasers(),
            'sessions' => ClassSession::query()->timetable(self::TIMETABLE_DAYS)->get(),
            'venue' => Location::venue(),
        ]);
    }

    /**
     * Active packages in display order with their one-member price (BR-3.2: a tier for 1 always exists).
     *
     * @return list<array{name: string, description: string|null, credits: int, from: string}>
     */
    private function packageTeasers(): array
    {
        return Package::query()->offered()->with('prices')->get()
            ->map(fn (Package $package) => [
                'name' => $package->name,
                'description' => $package->description,
                'credits' => $package->credits_per_member,
                'from' => Money::format($package->priceFor(1)),
            ])
            ->all();
    }
}
