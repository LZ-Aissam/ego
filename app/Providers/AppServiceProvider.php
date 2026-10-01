<?php

namespace App\Providers;

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
        \Illuminate\Support\Carbon::setLocale(config('app.locale'));

        \Illuminate\Support\Facades\View::composer('components.layouts.app', function ($view) {
            $view->with('systemNotifications', auth()->check()
                ? app(\App\Support\SystemNotifier::class)->pullUnseen(auth()->user())
                : []);
        });
    }
}
