<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\Unit;
use App\Observers\ItemObserver;
use App\Observers\UnitObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Item::observe(ItemObserver::class);
        Unit::observe(UnitObserver::class);

        $tooManyRequests = fn (Request $request, array $headers) => response()->json([
            'message' => 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat.',
        ], 429, $headers);

        RateLimiter::for('api-login', function (Request $request) use ($tooManyRequests) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(max(1, config('api.rate_limits.login_ip_per_minute')))
                    ->by('ip:'.hash('sha256', $ip))->response($tooManyRequests),
                Limit::perMinute(max(1, config('api.rate_limits.login_identity_per_minute')))
                    ->by('identity:'.hash('sha256', $email.'|'.$ip))->response($tooManyRequests),
            ];
        });

        RateLimiter::for('api-authenticated', fn (Request $request) => Limit::perMinute(max(1, config('api.rate_limits.authenticated_per_minute')))
            ->by('user:'.$request->user()->getAuthIdentifier())
            ->response($tooManyRequests));

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
