<?php

namespace App\Filament\Widgets;

use App\Enums\MembershipStatus;
use App\Filament\Resources\Households\HouseholdResource;
use App\Filament\Resources\Memberships\MembershipResource;
use App\Models\Household;
use App\Models\Membership;
use App\Support\Period;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// docs/05 § Dashboard 2: what is waiting for the admin, with a link to the list. Counts show even at 0.
class NeedsAttention extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Needs attention';

    protected int|array|null $columns = ['default' => 1, 'sm' => 3];

    protected function getStats(): array
    {
        $requests = Membership::query()->where('status', MembershipStatus::Requested)->count();
        $deletions = Household::query()->deletionRequested()->count();
        $ending = Membership::query()->where('status', MembershipStatus::Active)->where('ends_period', Period::current())->count();

        return [
            Stat::make('Membership requests', $requests)
                ->description('Waiting for approval')
                ->color($requests > 0 ? 'warning' : 'gray')
                ->url(MembershipResource::getUrl('index', ['tab' => 'requested'])),
            Stat::make('Deletion requests', $deletions)
                ->description('Waiting to be anonymised')
                ->color($deletions > 0 ? 'danger' : 'gray')
                ->url(HouseholdResource::getUrl('index', ['filters' => ['deletion_requested' => ['isActive' => true]]])),
            Stat::make('Ending this month', $ending)
                ->description('Cancelled by the customer')
                ->color($ending > 0 ? 'warning' : 'gray')
                ->url(MembershipResource::getUrl('index', ['tab' => 'ending'])),
        ];
    }
}
