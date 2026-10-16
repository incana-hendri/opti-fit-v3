<?php

use App\Actions\Admins\DeactivateAdmin;
use App\Enums\Role;
use App\Exceptions\AccountException;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\User;
use App\Notifications\ResetPassword;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// docs/05 § Admins, BR-9.4
beforeEach(function () {
    $this->admin = admin();
    $this->actingAs($this->admin);
});

it('lists the admins only, not customers (BR-1.7)', function () {
    $other = admin();
    $customer = customer();

    $this->get('/admin/admins')->assertOk();
    Livewire::test(ListAdmins::class)
        ->assertCanSeeTableRecords([$this->admin, $other])
        ->assertCanNotSeeTableRecords([$customer]);
});

it('keeps customers out of the Admins screen', function () {
    $this->actingAs(customer())->get('/admin/admins')->assertForbidden();
});

it('creates an admin with a password (BR-9.4)', function () {
    Livewire::test(CreateAdmin::class)
        ->fillForm([
            'first_name' => 'Sam', 'last_name' => 'Coach', 'email' => 'sam@example.com', 'phone' => '082 123 4567',
            'send_link' => false, 'password' => 'a-good-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = User::query()->where('email', 'sam@example.com')->firstOrFail();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-good-password', $admin->password))->toBeTrue()
        ->and($admin->household)->toBeNull();
});

it('requires a password when no set-password link is sent', function () {
    Livewire::test(CreateAdmin::class)
        ->fillForm(['first_name' => 'Sam', 'last_name' => 'Coach', 'email' => 'sam@example.com', 'send_link' => false, 'password' => ''])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);
});

it('sends a set-password link instead of choosing a password, and the admin lands in /admin after setting one (BR-1.7, 9.4)', function () {
    Notification::fake();

    Livewire::test(CreateAdmin::class)
        ->fillForm(['first_name' => 'Sam', 'last_name' => 'Coach', 'email' => 'sam@example.com', 'send_link' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = User::query()->where('email', 'sam@example.com')->firstOrFail();
    $token = null;
    Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    auth()->logout();
    $this->post('/reset-password', ['token' => $token, 'email' => 'sam@example.com', 'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1'])
        ->assertRedirect('/login');
    $this->post('/login', ['email' => 'sam@example.com', 'password' => 'Brand-new-pass-1'])
        ->assertRedirect('/admin');
    $this->get('/admin')->assertOk();
});

it('validates the email and the phone number', function () {
    Livewire::test(CreateAdmin::class)
        ->fillForm(['first_name' => 'Sam', 'last_name' => 'Coach', 'email' => $this->admin->email, 'phone' => 'not a number', 'send_link' => true])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique', 'phone' => 'regex']);
});

it('deactivates and reactivates another admin (BR-1.8)', function () {
    $other = admin();

    Livewire::test(ListAdmins::class)
        ->callAction(TestAction::make('deactivate')->table($other))
        ->assertNotified('Admin deactivated');
    expect($other->fresh()->isDeactivated())->toBeTrue();

    $this->actingAs($other->fresh())->get('/admin')->assertForbidden();
    $this->actingAs($this->admin);

    Livewire::test(ListAdmins::class)
        ->callAction(TestAction::make('reactivate')->table($other))
        ->assertNotified('Admin reactivated');
    expect($other->fresh()->isDeactivated())->toBeFalse();
});

it('does not let an admin deactivate themselves', function () {
    admin();

    Livewire::test(ListAdmins::class)
        ->callAction(TestAction::make('deactivate')->table($this->admin))
        ->assertNotified("You can't deactivate your own login.");
    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

it('never deactivates the last active admin', function () {
    $deactivated = User::factory()->admin()->deactivated()->create();

    expect(fn () => app(DeactivateAdmin::class)->handle($this->admin, $deactivated))
        ->toThrow(AccountException::class, 'There must always be at least one active admin.');
    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

it('has no delete action', function () {
    $other = admin();

    Livewire::test(ListAdmins::class)->assertActionDoesNotExist(TestAction::make('delete')->table($other));
});
