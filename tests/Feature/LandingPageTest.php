<?php

use App\Models\Location;
use App\Models\Package;

// docs/04 § Landing: hero, how it works, packages teaser, this week's timetable, venue card, footer.
it('shows the landing sections in order', function () {
    $this->get('/')->assertOk()->assertSeeInOrder([
        'Functional fitness for the',
        'How it works',
        'Add your family',
        'Choose a monthly package',
        'Book classes with credits',
        'Packages',
        "This week's timetable",
        'Where we train',
        'Gene Louw Activity Centre',
    ], false);
});

it('teases the active packages with their one-member price and links to pricing', function () {
    Package::query()->delete();
    Package::factory()->pricedAt([1 => 28000, 2 => 42000])->create(['name' => 'Once a week', 'sort_order' => 1]);
    Package::factory()->create(['name' => 'Twice a week', 'sort_order' => 2]);
    Package::factory()->inactive()->create(['name' => 'Retired package']);

    $this->get('/')
        ->assertSeeInOrder(['Once a week', 'R280', 'Twice a week', 'R520'])
        ->assertDontSee('Retired package')
        ->assertSee('href="'.route('pricing').'"', false);
});

it('hides the packages teaser when no package is active', function () {
    Package::query()->update(['is_active' => false]);

    $this->get('/')->assertOk()->assertDontSee('See all prices');
});

it('shows the venue card with an Open in Maps link only when the location has a map url', function () {
    $this->get('/')->assertSee('Where we train')->assertDontSee('Open in Maps');

    Location::query()->update(['map_url' => 'https://maps.example.com/gene-louw']);

    $this->get('/')->assertSee('Open in Maps')->assertSee('https://maps.example.com/gene-louw');
});

it('hides the venue card when there is no active location', function () {
    Location::query()->update(['is_active' => false]);

    $this->get('/')->assertOk()->assertDontSee('Where we train');
});

it('has a footer with contact details, the venue, the legal pages and the year', function () {
    $this->get('/')
        ->assertSee('info@opti-fit.co.za')
        ->assertSee('Tulip Street, Amanda Glen, Durbanville')
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('href="'.route('terms').'"', false)
        ->assertSee('&copy; '.now()->year, false);
});
