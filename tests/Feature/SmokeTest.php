<?php

use App\Models\Location;
use App\Models\Package;
use App\Models\ScheduleTemplate;
use App\Models\User;

// docs/09 § Smoke (HTTP): every public, customer and admin page returns 200 for the right user.
it('serves every public page', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/', '/pricing', '/timetable', '/privacy', '/terms', '/login', '/register', '/forgot-password']);

it('serves every customer page', function (string $path) {
    $this->actingAs(bookingCustomer())->get($path)->assertOk();
})->with(['/home', '/book', '/bookings', '/family', '/membership', '/membership/request', '/account']);

it('serves the onboarding page and the verify notice', function () {
    $this->actingAs(User::factory()->create())->get('/onboarding')->assertOk();
    $this->actingAs(User::factory()->unverified()->create())->get('/email/verify')->assertOk();
});

it('serves every admin page', function (string $path) {
    $this->actingAs(admin())->get($path)->assertOk();
})->with([
    '/admin', '/admin/reports', '/admin/settings', '/admin/admins', '/admin/admins/create',
    '/admin/households', '/admin/memberships', '/admin/packages', '/admin/packages/create',
    '/admin/class-sessions', '/admin/class-sessions/create', '/admin/locations', '/admin/locations/create',
    '/admin/schedule-templates', '/admin/schedule-templates/create',
]);

it('serves the admin record pages', function () {
    $this->actingAs(admin());
    $household = customer()->household;
    $session = classSession();

    foreach ([
        "/admin/households/{$household->id}",
        "/admin/class-sessions/{$session->id}",
        "/admin/class-sessions/{$session->id}/edit",
        '/admin/packages/'.Package::query()->firstOrFail()->id.'/edit',
        '/admin/locations/'.Location::query()->firstOrFail()->id.'/edit',
        '/admin/schedule-templates/'.ScheduleTemplate::query()->firstOrFail()->id.'/edit',
    ] as $path) {
        $this->get($path)->assertOk();
    }
});
