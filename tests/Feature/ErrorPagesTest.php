<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// docs/04 § Empty states, errors, feedback: 403/404/419/500 (and 503) are branded, with a link home.
it('shows the branded 404 page with a link home', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee("We couldn't find that page")
        ->assertSee('images/brand/logo-on-dark@1x.png', false)
        ->assertSee('Back to the start');
});

it('sends a signed-in customer home from the 404 page', function () {
    $this->actingAs(customer());

    $this->get('/family/no-such-page')->assertNotFound()->assertSee('href="'.route('home').'"', false);
});

it('shows the branded 403 page to a customer who opens the admin panel (BR-1.7)', function () {
    $this->actingAs(customer())
        ->get('/admin')
        ->assertForbidden()
        ->assertSee("You can't open this page");
});

it('shows the branded 419 page with a way back', function () {
    Route::get('/_test/expired', fn () => abort(419));

    $this->get('/_test/expired', ['referer' => url('/pricing')])
        ->assertStatus(419)
        ->assertSee('This page expired')
        ->assertSee('Go back');
});

it('shows the branded 500 page without exposing the exception', function () {
    config(['app.debug' => false]);
    Route::get('/_test/boom', fn () => throw new RuntimeException('secret detail'));

    $this->get('/_test/boom')
        ->assertStatus(500)
        ->assertSee('Something went wrong on our side')
        ->assertDontSee('secret detail');
});

it('renders the 500 and 503 pages without touching the database', function (string $view) {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $html = view($view)->render();

    expect($html)->toContain('OptiFit')->and($queries)->toBe(0);
})->with(['errors.500', 'errors.503']);
