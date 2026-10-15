<?php

namespace App\Filament\Concerns;

use Livewire\Attributes\On;

/**
 * For a relation manager: re-render when anything on the admin page changes data. After every action AppServiceProvider
 * dispatches `refresh-page`, which Filament's own pages already listen for, so the page and its tables stay in step.
 */
trait RefreshesWithPage
{
    #[On('refresh-page')]
    public function refreshRelationManager(): void {}
}
