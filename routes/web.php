<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\RequestAccountDeletionController;
use App\Http\Controllers\TimetableController;
use App\Livewire\BookingsPage;
use App\Livewire\BookPage;
use App\Livewire\Family;
use App\Livewire\Home;
use App\Livewire\MembershipPage;
use App\Livewire\Onboarding;
use App\Livewire\RequestForm;
use App\Models\ClassSession;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

Route::get('/', LandingController::class)->name('landing');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/timetable', TimetableController::class)->name('timetable');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');

// Signed-in customers (docs/04). Auth pages themselves are registered by Fortify.
Route::middleware(['auth', 'verified', 'customer'])->group(function () {
    Route::get('/onboarding', Onboarding::class)->name('onboarding');
    Route::get('/home', Home::class)->name('home');
    Route::get('/book', BookPage::class)->name('book');
    Route::get('/bookings', BookingsPage::class)->name('bookings');
    Route::get('/family', Family::class)->name('family');
    Route::get('/membership', MembershipPage::class)->name('membership');
    Route::get('/membership/request', RequestForm::class)->name('membership.request');
    Route::view('/account', 'customer.account')->name('account');
    Route::post('/account/deletion-request', RequestAccountDeletionController::class)->name('account.deletion-request');
});

// Visual QA for the components in docs/08. Never served in production.
Route::prefix('_styleguide')->group(function () {
    Route::get('/', function () {
        abort_if(app()->isProduction(), 404);

        View::share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'sg_phone' => 'Please enter a valid mobile number.',
        ])));

        $days = collect(range(0, 13))->map(fn (int $i) => [
            'date' => today()->addDays($i),
            'hasSessions' => today()->addDays($i)->isWeekday(),
        ])->all();

        $venue = new Location(['name' => 'Gene Louw Activity Centre']);
        $sessions = collect([[today()->setTime(16, 0), 6], [today()->addDay()->setTime(5, 0), 14], [today()->addDay()->setTime(16, 0), 15]])
            ->map(fn (array $slot) => (new ClassSession(['title' => 'Functional Fitness', 'starts_at' => $slot[0], 'duration_minutes' => 45, 'capacity' => 15, 'occupying_bookings_count' => $slot[1], 'guest_places' => null]))
                ->setRelation('location', $venue));

        return view('styleguide.index', ['days' => $days, 'sessions' => $sessions]);
    });

    Route::get('/app', function () {
        abort_if(app()->isProduction(), 404);

        return view('styleguide.app', ['user' => new User(['first_name' => 'Anna', 'last_name' => 'Smith'])]);
    });

    // Previews of the error pages (docs/04), with their real status codes, for visual QA.
    Route::get('/errors/{code}', function (int $code) {
        abort_if(app()->isProduction() || ! in_array($code, [403, 404, 419, 500, 503], true), 404);

        return response()->view("errors.{$code}", [], $code);
    })->whereNumber('code');

    Route::get('/auth', function () {
        abort_if(app()->isProduction(), 404);

        return view('styleguide.auth');
    });
});
