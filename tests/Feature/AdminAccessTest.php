<?php

use App\Models\User;

it('lets an active admin into the panel (BR-1.7)', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('forbids customers from the panel (BR-1.7)', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('forbids deactivated admins from the panel (BR-1.8)', function () {
    $this->actingAs(User::factory()->admin()->deactivated()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('sends guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
