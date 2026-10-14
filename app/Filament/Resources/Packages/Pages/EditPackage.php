<?php

namespace App\Filament\Resources\Packages\Pages;

use App\Filament\Resources\Packages\PackageResource;
use App\Models\Package;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // BR-3.4: the form's "Active" switch explains why a used package can only be archived.
            DeleteAction::make()->visible(fn (Package $record) => $record->isDeletable()),
        ];
    }
}
