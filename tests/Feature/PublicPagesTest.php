<?php

it('renders the landing page in the brand', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Functional fitness for the')
        ->assertSee('images/brand/logo-on-dark-tagline@1x.png', false)
        ->assertSee('<meta name="theme-color" content="#0D0918">', false)
        ->assertSee('info@opti-fit.co.za');
});

it('renders the styleguide pages outside production', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/_styleguide', '/_styleguide/app', '/_styleguide/auth']);

it('hides the styleguide in production', function (string $path) {
    app()->detectEnvironment(fn () => 'production');

    $this->get($path)->assertNotFound();
})->with(['/_styleguide', '/_styleguide/app', '/_styleguide/auth']);

it('previews the error pages outside production with their status codes', function (int $code) {
    $this->get("/_styleguide/errors/{$code}")->assertStatus($code)->assertSee((string) $code);
})->with([403, 404, 419, 500, 503]);

it('hides the error previews in production and for other codes', function () {
    $this->get('/_styleguide/errors/418')->assertNotFound()->assertDontSee('418');

    app()->detectEnvironment(fn () => 'production');

    $this->get('/_styleguide/errors/500')->assertNotFound()->assertDontSee('Something went wrong');
});
