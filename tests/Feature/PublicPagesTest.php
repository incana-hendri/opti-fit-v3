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
