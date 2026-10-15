<?php

use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Models\Membership;
use App\Models\Package;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(admin());
});

it('renders the packages pages', function () {
    $package = Package::query()->first();

    $this->get('/admin/packages')->assertOk()->assertSee('2× per week');
    $this->get('/admin/packages/create')->assertOk();
    $this->get("/admin/packages/{$package->id}/edit")->assertOk();
});

it('lists the packages with their price for 1 member, in order (docs/05)', function () {
    Livewire::test(ListPackages::class)
        ->assertCanSeeTableRecords(Package::query()->orderBy('sort_order')->get(), inOrder: true)
        ->assertSee('R520')
        ->assertSee('R280');
});

it('creates a package with price tiers entered in rand (BR-3.1, 3.2)', function () {
    Livewire::test(CreatePackage::class)
        ->fillForm([
            'name' => 'Boot camp',
            'description' => 'Saturday mornings',
            'sessions_per_week' => 1,
            'credits_per_member' => 4,
            'is_active' => true,
            'sort_order' => 10,
            'prices' => [
                ['member_count' => 1, 'price_cents' => 300],
                ['member_count' => 3, 'price_cents' => 750.5],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = Package::query()->where('name', 'Boot camp')->firstOrFail();
    expect($package->prices->pluck('price_cents', 'member_count')->all())->toBe([1 => 30000, 3 => 75050])
        ->and($package->priceFor(2))->toBe(30000)
        ->and($package->priceFor(8))->toBe(75050);
});

it('requires a price tier for 1 member (BR-3.2)', function () {
    Livewire::test(CreatePackage::class)
        ->fillForm([
            'name' => 'No single price',
            'sessions_per_week' => 1,
            'credits_per_member' => 4,
            'prices' => [['member_count' => 2, 'price_cents' => 500]],
        ])
        ->call('create')
        ->assertHasFormErrors(['prices']);

    expect(Package::query()->where('name', 'No single price')->exists())->toBeFalse();
});

it('rejects repeated member counts and prices of zero (BR-3.2)', function () {
    Livewire::test(CreatePackage::class)
        ->fillForm([
            'name' => 'Messy tiers',
            'sessions_per_week' => 1,
            'credits_per_member' => 4,
            'prices' => [
                ['member_count' => 1, 'price_cents' => 300],
                ['member_count' => 1, 'price_cents' => 0],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Package::query()->where('name', 'Messy tiers')->exists())->toBeFalse();
});

it('shows existing prices in rand when editing and saves changes without touching memberships (BR-3.5)', function () {
    $package = Package::factory()->pricedAt([1 => 52000, 2 => 78000])->create();
    $membership = Membership::factory()->for($package)->create(['monthly_price_cents' => 52000]);

    $component = Livewire::test(EditPackage::class, ['record' => $package->getRouteKey()]);
    $prices = collect($component->get('data.prices'))->pluck('price_cents')->values()->all();
    expect($prices)->toBe([520.0, 780.0]);

    $component->fillForm(['credits_per_member' => 10])->call('save')->assertHasNoFormErrors();

    expect($package->fresh()->credits_per_member)->toBe(10)
        ->and($membership->fresh()->monthly_price_cents)->toBe(52000);
});

it('offers delete only for a package no membership ever used (BR-3.4)', function () {
    $unused = Package::factory()->create();
    $used = Package::factory()->create();
    Membership::factory()->for($used)->create();

    Livewire::test(ListPackages::class)
        ->assertActionVisible(TestAction::make('delete')->table($unused))
        ->assertActionHidden(TestAction::make('delete')->table($used));

    Livewire::test(EditPackage::class, ['record' => $used->getRouteKey()])
        ->assertActionHidden('delete')
        ->assertSee("can't be deleted");

    Livewire::test(EditPackage::class, ['record' => $unused->getRouteKey()])->assertActionVisible('delete');
});

it('deletes an unused package with its tiers (BR-3.4)', function () {
    $package = Package::factory()->create();

    Livewire::test(ListPackages::class)->callAction(TestAction::make('delete')->table($package));

    expect(Package::query()->find($package->id))->toBeNull()
        ->and($package->prices()->count())->toBe(0);
});

it('archives and unarchives a package that memberships use (BR-3.3, 3.4)', function () {
    $package = Package::factory()->create();
    Membership::factory()->for($package)->create();

    Livewire::test(ListPackages::class)
        ->callAction(TestAction::make('archive')->table($package))
        ->assertActionHidden(TestAction::make('archive')->table($package));
    expect($package->fresh()->is_active)->toBeFalse();

    Livewire::test(ListPackages::class)->callAction(TestAction::make('unarchive')->table($package));
    expect($package->fresh()->is_active)->toBeTrue();
});
