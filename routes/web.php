<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

Route::view('/', 'pages.landing')->name('landing');

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
