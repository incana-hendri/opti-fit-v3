<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Models\Package;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('sessions_per_week')
                            ->label('Sessions per week')
                            ->helperText('For display only. It is not a booking limit.')
                            ->numeric()->integer()->required()->minValue(1)->maxValue(7),
                        Textarea::make('description')->maxLength(500)->rows(2)->columnSpanFull(),
                        TextInput::make('credits_per_member')
                            ->label('Credits per member each month')
                            ->helperText('Usually sessions per week × 4.')
                            ->numeric()->integer()->required()->minValue(1)->maxValue(1000),
                        TextInput::make('sort_order')
                            ->helperText('Lower numbers come first. You can also drag rows in the list.')
                            ->numeric()->integer()->default(0)->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText(fn (?Package $record) => $record && ! $record->isDeletable()
                                ? "This package has memberships, so it can't be deleted. Switch it off to archive it: existing memberships continue, but it is hidden from the pricing page and new requests."
                                : 'Switch it off to hide the package from the pricing page and new requests.')
                            ->default(true),
                    ]),
                Section::make('Price tiers')
                    ->description('The price for a family is the tier with the largest member count that is not above its size, so the highest tier means "or more". A tier for 1 member is required.')
                    ->schema([
                        Repeater::make('prices')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Add tier')
                            ->minItems(1)
                            ->default([['member_count' => 1]])
                            ->columns(2)
                            ->rules([fn (): Closure => self::mustPriceOneMember(...)])
                            ->schema([
                                TextInput::make('member_count')
                                    ->label('Members')
                                    ->numeric()->integer()->required()->minValue(1)->maxValue(50)->distinct(),
                                TextInput::make('price_cents')
                                    ->label('Price per month')
                                    ->prefix('R')
                                    ->numeric()->required()->minValue(0.01)->step(0.01)
                                    ->formatStateUsing(fn (?int $state) => $state === null ? null : $state / 100)
                                    ->dehydrateStateUsing(fn (int|float|string|null $state) => Money::toCents($state ?? 0)),
                            ]),
                    ]),
            ]);
    }

    // BR-3.2: without a tier for 1 member, a one-person household couldn't be priced.
    private static function mustPriceOneMember(string $attribute, mixed $value, Closure $fail): void
    {
        $tiers = collect(is_array($value) ? $value : []);

        if (! $tiers->contains(fn (array $tier) => (int) ($tier['member_count'] ?? 0) === 1)) {
            $fail('Add a price tier for 1 member.');
        }
    }
}
