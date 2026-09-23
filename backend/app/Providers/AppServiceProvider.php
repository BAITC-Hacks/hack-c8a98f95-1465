<?php

namespace App\Providers;

use App\Services\AiQuestions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('ai', function (Request $request) {
            if (AiQuestions::provider() === 'mock') {
                return Limit::none();
            }

            return [
                Limit::perMinute(config('ai.requests_per_minute'))->by('ai-minute:'.$request->ip()),
                Limit::perDay(config('ai.requests_per_day'))->by('ai-daily-total'),
            ];
        });
    }
}
