<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        RateLimiter::for("ai-call", function (Request $request) {
            return [
                Limit::perDay(config('app.limits.per_day')),
                Limit::perMinute(config('app.limits.per_minute'))->by('minute:' . $request->user()->id),
                Limit::perHour(config('app.limits.per_hour'))->by('hour:' . $request->user()->id),
            ];
        });
    }
}
