<?php

namespace App\Filament\Resources\Households\Schemas;

use App\Models\Household;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

// The Overview tab (docs/05 § Households).
class HouseholdInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components([
                Section::make('Anonymised')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columnSpanFull()
                    ->visible(fn (Household $record) => $record->isAnonymised())
                    ->description('The personal data of this household was removed (BR-9.6). The credit history and past bookings are kept for the reports.')
                    ->schema([
                        TextEntry::make('anonymised_at')->label('Anonymised on')->dateTime('j M Y, H:i'),
                    ]),
                Section::make('Account holder')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.first_name')->label('First name'),
                        TextEntry::make('user.last_name')->label('Last name'),
                        TextEntry::make('user.email')->label('Email')->copyable(),
                        TextEntry::make('user.phone')->label('Phone')->copyable(),
                        TextEntry::make('user.date_of_birth')->label('Date of birth')->date('j M Y'),
                        TextEntry::make('user.created_at')->label('Joined')->date('j M Y'),
                        TextEntry::make('user.deactivated_at')->label('Login deactivated')->dateTime('j M Y, H:i')->placeholder('No'),
                        TextEntry::make('user.deletion_requested_at')->label('Deletion requested')->dateTime('j M Y, H:i')->placeholder('No'),
                    ]),
                Section::make('Emergency contact')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('emergency_contact_name')->label('Name')->placeholder('Not given yet'),
                        TextEntry::make('emergency_contact_phone')->label('Phone')->placeholder('Not given yet')->copyable(),
                    ]),
                Section::make('Consent')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.terms_version')->label('Terms version'),
                        TextEntry::make('user.terms_accepted_at')->label('Terms accepted')->dateTime('j M Y, H:i'),
                        IconEntry::make('user.marketing_opt_in')->label('Marketing opt-in')->boolean(),
                    ]),
            ]);
    }
}
