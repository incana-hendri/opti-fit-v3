<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use UnitEnum;

// docs/05 § Navigation groups: the dashboard is the "Today" group. Its widgets are discovered in app/Filament/Widgets.
class Dashboard extends BaseDashboard
{
    protected static string|UnitEnum|null $navigationGroup = 'Today';

    public function getColumns(): int|array
    {
        return 1;
    }
}
