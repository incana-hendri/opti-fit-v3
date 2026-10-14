<?php

namespace App\Providers;

use App\Models\Settings;
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

        View::composer('components.layouts.public', fn ($view) => $view->with('settings', Settings::current()));

        // docs/06: replies to any email go to the studio's contact email.
        Event::listen(function (MessageSending $event): void {
            if ($event->message->getReplyTo() === []) {
                $event->message->replyTo(Settings::current()->contact_email);
            }
        });
    }
}
