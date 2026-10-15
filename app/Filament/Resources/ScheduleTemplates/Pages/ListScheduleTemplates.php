<?php

namespace App\Filament\Resources\ScheduleTemplates\Pages;

use App\Actions\Sessions\GenerateSessions;
use App\Filament\Resources\ScheduleTemplates\ScheduleTemplateResource;
use App\Models\ScheduleTemplate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListScheduleTemplates extends ListRecords
{
    protected static string $resource = ScheduleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateSessions(),
            CreateAction::make(),
        ];
    }

    // BR-8.2: a date range (default today to the end of next month) and the templates to use (default all active).
    private function generateSessions(): Action
    {
        return Action::make('generate')
            ->label('Generate sessions')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->modalHeading('Generate sessions')
            ->modalDescription('Creates one class for each matching weekday and skips days that already have one from the same template.')
            ->modalSubmitActionLabel('Generate')
            ->fillForm(fn () => [
                'from' => today()->toDateString(),
                'until' => today()->addMonthNoOverflow()->endOfMonth()->toDateString(),
                'templates' => ScheduleTemplate::query()->where('is_active', true)->pluck('id')->all(),
            ])
            ->schema([
                DatePicker::make('from')->required(),
                DatePicker::make('until')->required()->afterOrEqual('from'),
                CheckboxList::make('templates')
                    ->options(fn () => ScheduleTemplate::query()->orderBy('weekday')->orderBy('start_time')->get()
                        ->mapWithKeys(fn (ScheduleTemplate $template) => [$template->id => $template->weekday->shortLabel().' '.substr($template->start_time, 0, 5).' '.$template->title.($template->is_active ? '' : ' (inactive)')]))
                    ->required(),
            ])
            ->action(function (array $data) {
                $result = app(GenerateSessions::class)->handle(
                    ScheduleTemplate::query()->whereKey($data['templates'])->get(),
                    Carbon::parse($data['from']),
                    Carbon::parse($data['until']),
                );

                Notification::make()
                    ->title("Created {$result['created']} ".str('session')->plural($result['created']).", skipped {$result['skipped']} existing")
                    ->success()
                    ->send();
            });
    }
}
