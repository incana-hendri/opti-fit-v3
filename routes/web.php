<?php

use App\Http\Controllers\PricingController;
use App\Http\Controllers\RequestAccountDeletionController;
use App\Livewire\Family;
use App\Livewire\Home;
use App\Livewire\MembershipPage;
use App\Livewire\Onboarding;
use App\Livewire\RequestForm;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

Route::view('/', 'pages.landing')->name('landing');
Route::get('/pricing', PricingController::class)->name('pricing');

// Signed-in customers (docs/04). Auth pages themselves are registered by Fortify.
Route::middleware(['auth', 'verified', 'customer'])->group(function () {
    Route::get('/onboarding', Onboarding::class)->name('onboarding');
    Route::get('/home', Home::class)->name('home');
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

        return view('styleguide.index', ['days' => $days]);
    });

    Route::get('/app', function () {
        abort_if(app()->isProduction(), 404);

        return view('styleguide.app', ['user' => new User(['first_name' => 'Anna', 'last_name' => 'Smith'])]);
    });

    Route::get('/auth', function () {
        abort_if(app()->isProduction(), 404);

        return view('styleguide.auth');
    });
});
