<?php

namespace App\Filament\Resources\Memberships\Pages;

use App\Enums\MembershipStatus;
use App\Filament\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use App\Support\Period;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMemberships extends ListRecords
{
    protected static string $resource = MembershipResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'requested';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'requested' => Tab::make('Requested')
                ->badge(Membership::query()->where('status', MembershipStatus::Requested)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MembershipStatus::Requested)),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MembershipStatus::Active)),
            'paused' => Tab::make('Paused')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MembershipStatus::Paused)),
            // BR-4.8: customers who cancelled; their last grant is this month's.
            'ending' => Tab::make('Ending this month')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', MembershipStatus::Active)
                    ->where('ends_period', Period::current())),
            'closed' => Tab::make('Cancelled / declined')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [MembershipStatus::Cancelled, MembershipStatus::Declined])),
        ];
    }
}
