<?php

use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Filament\Resources\ScheduleTemplates\Pages\CreateScheduleTemplate;
use App\Filament\Resources\ScheduleTemplates\Pages\EditScheduleTemplate;
use App\Filament\Resources\ScheduleTemplates\Pages\ListScheduleTemplates;
use App\Models\ClassSession;
use App\Models\Location;
use App\Models\ScheduleTemplate;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->actingAs(admin());
});

// --- Locations (BR-9.2) ---

it('creates and edits a location', function () {
    Livewire::test(CreateLocation::class)
        ->fillForm(['name' => 'Community Hall', 'address' => '1 Church Street, Durbanville', 'map_url' => 'https://maps.example.com/hall'])
        ->call('create')
        ->assertHasNoFormErrors();

    $location = Location::query()->where('name', 'Community Hall')->sole();
    expect($location->is_active)->toBeTrue();

    Livewire::test(EditLocation::class, ['record' => $location->id])
        ->fillForm(['address' => '2 Church Street'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($location->fresh()->address)->toBe('2 Church Street');
});

it('lists locations and archives one that nothing uses (BR-9.2)', function () {
    $unused = Location::factory()->create();

    Livewire::test(ListLocations::class)
        ->assertCanSeeTableRecords([$unused])
        ->callAction(TestAction::make('archive')->table($unused))
        ->assertNotified("{$unused->name} archived");

    expect($unused->fresh()->is_active)->toBeFalse();
});

it('refuses to archive a location with an upcoming class or an active template (BR-9.2)', function () {
    $location = Location::query()->sole();

    Livewire::test(ListLocations::class)
        ->callAction(TestAction::make('archive')->table($location))
        ->assertNotified();
    expect($location->fresh()->is_active)->toBeTrue();

    Livewire::test(EditLocation::class, ['record' => $location->id])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasFormErrors(['is_active']);
    expect($location->fresh()->is_active)->toBeTrue();
});

it('lets an archived location come back', function () {
    $location = Location::factory()->inactive()->create();

    Livewire::test(ListLocations::class)
        ->callAction(TestAction::make('unarchive')->table($location));

    expect($location->fresh()->is_active)->toBeTrue();
});

// --- Templates (BR-8.1, 8.2, 8.3) ---

it('lists the templates by weekday and time (BR-8.1)', function () {
    ScheduleTemplate::query()->delete();
    $friday = ScheduleTemplate::factory()->on(5, '05:00:00')->create();
    $mondayLate = ScheduleTemplate::factory()->on(1, '16:00:00')->create();
    $mondayEarly = ScheduleTemplate::factory()->on(1, '05:00:00')->create();

    Livewire::test(ListScheduleTemplates::class)
        ->assertCanSeeTableRecords([$mondayEarly, $mondayLate, $friday], inOrder: true);
});

it('creates and edits a template (BR-8.1)', function () {
    $location = Location::query()->sole();

    Livewire::test(CreateScheduleTemplate::class)
        ->fillForm([
            'title' => 'Saturday Boot Camp', 'location_id' => $location->id, 'weekday' => 6, 'start_time' => '08:00',
            'duration_minutes' => 60, 'capacity' => 20, 'min_participants' => 5, 'cutoff_hours' => 4,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = ScheduleTemplate::query()->where('title', 'Saturday Boot Camp')->sole();
    expect($template->weekday->label())->toBe('Saturday')
        ->and($template->capacity)->toBe(20)
        ->and($template->cutoff_hours)->toBe(4)
        ->and($template->is_active)->toBeTrue();

    Livewire::test(EditScheduleTemplate::class, ['record' => $template->id])
        ->fillForm(['capacity' => 18])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($template->fresh()->capacity)->toBe(18);
});

it('generates sessions for the chosen range and templates and reports the counts (BR-8.2)', function () {
    ScheduleTemplate::query()->delete();
    $monday = ScheduleTemplate::factory()->on(1, '05:00:00')->create();
    ScheduleTemplate::factory()->on(2, '15:30:00')->create();

    Livewire::test(ListScheduleTemplates::class)
        ->mountAction('generate')
        ->assertActionDataSet(['from' => '2026-10-16', 'until' => '2026-11-30', 'templates' => ScheduleTemplate::query()->pluck('id')->all()])
        ->setActionData(['from' => '2026-10-16', 'until' => '2026-11-01', 'templates' => [$monday->id]])
        ->callMountedAction()
        ->assertNotified('Created 2 sessions, skipped 0 existing');

    expect(ClassSession::query()->count())->toBe(2);

    Livewire::test(ListScheduleTemplates::class)
        ->callAction('generate', ['from' => '2026-10-16', 'until' => '2026-11-08', 'templates' => [$monday->id]])
        ->assertNotified('Created 1 session, skipped 2 existing');
});

it('defaults the generator to the active templates only (BR-8.2)', function () {
    ScheduleTemplate::query()->delete();
    $active = ScheduleTemplate::factory()->create();
    ScheduleTemplate::factory()->inactive()->create();

    Livewire::test(ListScheduleTemplates::class)
        ->mountAction('generate')
        ->assertActionDataSet(['templates' => [$active->id]]);
});

it('can switch a template off without touching its sessions (BR-8.3)', function () {
    $template = ScheduleTemplate::query()->first();
    $session = ClassSession::factory()->create(['schedule_template_id' => $template->id]);

    Livewire::test(ListScheduleTemplates::class)
        ->callAction(TestAction::make('deactivate')->table($template));

    expect($template->fresh()->is_active)->toBeFalse()
        ->and($session->fresh()->capacity)->toBe(15);
});
