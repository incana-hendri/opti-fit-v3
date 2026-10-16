<?php

// docs/07 § Security checklist: security headers.
it('sends the security headers on public, customer, admin and error pages', function (string $path, bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(customer());
    }

    $this->get($path)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
})->with([
    'public page' => ['/', false],
    'customer page' => ['/home', true],
    'admin login' => ['/admin/login', false],
    'not found' => ['/no-such-page', false],
]);
