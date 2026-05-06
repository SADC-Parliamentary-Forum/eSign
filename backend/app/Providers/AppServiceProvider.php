<?php

namespace App\Providers;

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
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = mb_strtolower($email) . '|' . $request->ip();

            return [
                Limit::perMinute(10)->by($key),
            ];
        });

        \Illuminate\Auth\Notifications\VerifyEmail::createUrlUsing(function ($notifiable) {
            $frontendUrl = config('app.frontend_url');

            $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'verification.verify',
                \Carbon\Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Parse the generated signed URL to extract signature and expires
            $parsed = parse_url($verifyUrl);
            parse_str($parsed['query'] ?? '', $queryParams);

            return $frontendUrl . '/auth/verify-email?id=' . $notifiable->getKey() .
                '&hash=' . sha1($notifiable->getEmailForVerification()) .
                '&expires=' . ($queryParams['expires'] ?? '') .
                '&signature=' . ($queryParams['signature'] ?? '');
        });

        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($notifiable, $token) {
            $frontendUrl = config('app.frontend_url');

            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . $notifiable->getEmailForPasswordReset();
        });
    }
}
