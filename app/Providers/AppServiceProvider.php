<?php

namespace App\Providers;

use App\Contracts\CountryMetadataProvider;
use App\Contracts\SignupRepository;
use App\Repositories\EloquentSignupRepository;
use App\Support\Countries\CachedCountryMetadataProvider;
use App\Support\Countries\InternationalCountryMetadataProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registers application service bindings used by the landing-page flow.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register repository and metadata-provider abstractions.
     */
    public function register(): void
    {
        $this->app->bind(SignupRepository::class, EloquentSignupRepository::class);

        $this->app->singleton(CountryMetadataProvider::class, function ($application): CountryMetadataProvider {
            return new CachedCountryMetadataProvider(
                new InternationalCountryMetadataProvider,
                $application->make(CacheRepository::class),
            );
        });
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'signup',
            fn (Request $request): array => [
                Limit::perMinute(10)->by($this->rateLimitKey($request, 'signup')),
                Limit::perMinute(30)->by($this->ipRateLimitKey($request, 'signup')),
            ],
        );

        RateLimiter::for(
            'login',
            fn (Request $request): array => [
                Limit::perMinute(5)->by($this->rateLimitKey($request, 'login')),
                Limit::perMinute(20)->by($this->ipRateLimitKey($request, 'login')),
            ],
        );

        RateLimiter::for(
            'signup-challenge',
            fn (Request $request): Limit => Limit::perMinute(30)->by($this->ipRateLimitKey($request, 'signup-challenge')),
        );
    }

    /**
     * Build a rate-limit key without storing raw email addresses in the cache key.
     */
    private function rateLimitKey(Request $request, string $prefix): string
    {
        $emailInput = $request->input('email');
        $normalizedEmail = is_scalar($emailInput) ? strtolower(trim((string) $emailInput)) : 'anonymous';
        $hashedEmail = hash('sha256', $normalizedEmail);
        $ipAddress = $request->ip() ?: 'unknown';

        return sprintf('%s|%s|%s', $prefix, $ipAddress, $hashedEmail);
    }

    /**
     * Build a coarse IP-only rate-limit key for broader spam protection.
     */
    private function ipRateLimitKey(Request $request, string $prefix): string
    {
        return sprintf('%s|%s', $prefix, $request->ip() ?: 'unknown');
    }
}
