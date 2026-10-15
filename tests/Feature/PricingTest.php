<?php

use App\Models\Package;

it('shows the seeded packages and prices to visitors (BR-3.6)', function () {
    $this->get('/pricing')
        ->assertOk()
        ->assertSee('Simple monthly pricing')
        ->assertSee('1× per week')
        ->assertSee('2× per week')
        ->assertSee('3× per week')
        ->assertSee('R520')
        ->assertSee('credits / month')
        ->assertSee('≈ R65 per class')
        ->assertSee('How many family members will train?')
        ->assertSee('Credits expire');
});

it('has a family size selector from 1 to 5+ that carries a price for every size (BR-3.6)', function () {
    $html = $this->get('/pricing')->assertOk()->getContent();

    foreach ([1, 2, 3, 4, 5] as $size) {
        expect($html)->toContain('name="family-size" value="'.$size.'"');
    }
    // The 2× package: 1 member R520 up to 5+ members R1 248, with 8 to 40 credits.
    expect($html)->toContain('R1 248')->toContain('R1 144')->toContain('40+');
});

it('hides archived packages (BR-3.3)', function () {
    Package::factory()->inactive()->create(['name' => 'Retired package']);
    Package::factory()->create(['name' => 'Fresh package']);

    $this->get('/pricing')->assertOk()->assertSee('Fresh package')->assertDontSee('Retired package');
});

it('sends visitors to register and customers to the request form (docs/04)', function () {
    $package = Package::query()->orderBy('sort_order')->first();

    $this->get('/pricing')->assertSee(route('register'), false);

    $this->actingAs(customer())->get('/pricing')
        ->assertSee(route('membership.request', ['package' => $package->id]), false);
});

it('shows an empty state when no package is on sale', function () {
    Package::query()->delete();

    $this->get('/pricing')->assertOk()->assertSee('Packages are coming soon');
});

it('shows the cutoff from the settings in the FAQ (D2)', function () {
    $this->get('/pricing')->assertSee('up to 8 hours before a class starts');
});
