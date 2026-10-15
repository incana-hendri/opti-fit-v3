<?php

namespace App\Actions\Sessions;

use App\Models\ClassSession;
use App\Models\ScheduleTemplate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// BR-8.2: one session per matching weekday, skipping dates where the template already has one.
class GenerateSessions
{
    /**
     * @param  Collection<int, ScheduleTemplate>  $templates
     * @return array{created: int, skipped: int}
     */
    public function handle(Collection $templates, CarbonInterface $from, CarbonInterface $to): array
    {
        $counts = ['created' => 0, 'skipped' => 0];

        DB::transaction(function () use ($templates, $from, $to, &$counts) {
            foreach ($templates as $template) {
                $existing = $template->classSessions()
                    ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->pluck('starts_at')
                    ->map(fn (Carbon $startsAt) => $startsAt->format('Y-m-d H:i:s'));

                foreach ($this->startsAt($template, $from, $to) as $startsAt) {
                    if ($existing->contains($startsAt->format('Y-m-d H:i:s'))) {
                        $counts['skipped']++;

                        continue;
                    }

                    $this->create($template, $startsAt);
                    $counts['created']++;
                }
            }
        });

        return $counts;
    }

    /**
     * @return list<Carbon>
     */
    private function startsAt(ScheduleTemplate $template, CarbonInterface $from, CarbonInterface $to): array
    {
        $startsAt = [];

        for ($day = Carbon::instance($from)->startOfDay(); $day->lte($to); $day->addDay()) {
            if ($day->dayOfWeekIso === $template->weekday) {
                $startsAt[] = $day->copy()->setTimeFromTimeString($template->start_time);
            }
        }

        return $startsAt;
    }

    private function create(ScheduleTemplate $template, Carbon $startsAt): void
    {
        ClassSession::query()->create([
            'schedule_template_id' => $template->id,
            'location_id' => $template->location_id,
            'title' => $template->title,
            'starts_at' => $startsAt,
            'duration_minutes' => $template->duration_minutes,
            'capacity' => $template->capacity,
            'min_participants' => $template->min_participants,
            'cutoff_hours' => $template->cutoff_hours,
            'description' => $template->description,
        ]);
    }
}
