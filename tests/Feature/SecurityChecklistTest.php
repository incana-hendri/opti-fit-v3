<?php

use App\Providers\AppServiceProvider;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

// docs/07 § Security checklist: the items that no other test already proves.
it('has a CSRF token in every POST form (docs/07)', function () {
    $missing = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with((string) $file, '.blade.php') || str_contains((string) $file, '/styleguide/')) {
            continue;
        }

        $lines = file((string) $file);

        foreach ($lines as $index => $line) {
            if (preg_match('/<form[^>]*method="POST"/i', $line) && ! str_contains(implode('', array_slice($lines, $index, 4)), '@csrf')) {
                $missing[] = $file->getFilename().':'.($index + 1);
            }
        }
    }

    expect($missing)->toBe([]);
});

it('regenerates the session id when someone logs in (docs/07)', function () {
    $user = customer();

    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    expect(session()->getId())->not->toBe($before);
});

it('throttles the admin panel login too (docs/07)', function () {
    $admin = admin();

    foreach (range(1, 6) as $attempt) {
        $page = Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'wrong'])
            ->call('authenticate');
    }

    $page->assertNotified('Too many login attempts');
    $this->assertGuest();
});

it('forces HTTPS in production (docs/07)', function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    expect(URL::to('/home'))->toStartWith('https://');
});

it('does not force HTTPS outside production, where Sail serves plain HTTP', function () {
    expect(URL::to('/home'))->toStartWith('http://');
});
