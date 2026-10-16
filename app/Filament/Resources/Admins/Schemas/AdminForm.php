<?php

namespace App\Filament\Resources\Admins\Schemas;

use App\Support\Phone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('first_name')->required()->maxLength(255),
                TextInput::make('last_name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique('users', 'email'),
                TextInput::make('phone')->tel()->rules(Phone::rules(required: false)),
                Toggle::make('send_link')
                    ->label('Send a set-password link by email')
                    ->helperText('They choose their own password from the link. Switch off to set one here.')
                    ->default(true)
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (Get $get) => ! $get('send_link'))
                    ->visible(fn (Get $get) => ! $get('send_link'))
                    ->columnSpanFull(),
            ]);
    }
}
