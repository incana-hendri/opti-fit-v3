<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Settings;
use Filament\Actions\Action;
use Filament\Actions\Events\ActionCalled;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        View::composer('components.layouts.public', fn ($view) => $view->with(['settings' => Settings::current(), 'venue' => Location::venue()]));

        // Admins see the result of an action at once, wherever it shows: after any admin action the page and its relation
        // managers all re-render (Filament pages listen for `refresh-page`, our relation managers too).
        Event::listen(ActionCalled::class, fn (Action $action) => $action->getLivewire()->dispatch('refresh-page'));

        // docs/06: replies to any email go to the studio's contact email.
        Event::listen(function (MessageSending $event): void {
            if ($event->message->getReplyTo() === []) {
                $event->message->replyTo(Settings::current()->contact_email);
            }
        });
    }
}
