<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

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
        // Tailwind pagination
        Paginator::useTailwind();

        // 🔥 Last login tracking
        Event::listen(Login::class, function ($event) {
            $user = $event->user;

            if ($user) {
                $user->forceFill([
                    'last_login_at' => now(),
                ])->save();
            }
        });
    }
}