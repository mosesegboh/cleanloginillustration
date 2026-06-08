<?php

declare(strict_types=1);

namespace App\Support\Security;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

/**
 * Issues and validates sessionless anti-spam challenges for the signup form.
 */
final class SignupSpamChallenge
{
    private const CACHE_KEY_PREFIX = 'signup-challenge:';

    /**
     * Create the challenge service with the cache used to enforce one-time challenge use.
     */
    public function __construct(
        private readonly CacheRepository $cacheRepository,
    ) {}

    /**
     * Issue a signed challenge that does not require cookies or server-side session state.
     *
     * @return array{signupStartedAt: string, signupChallengeNonce: string, signupChallengeToken: string}
     */
    public function issue(?CarbonImmutable $issuedAt = null): array
    {
        $startedAt = (string) ($issuedAt ?? CarbonImmutable::now('UTC'))->getTimestamp();
        $nonce = bin2hex(random_bytes(16));
        $token = $this->signature($startedAt, $nonce);

        $wasChallengeStored = $this->cacheRepository->put(
            $this->cacheKey($nonce),
            $startedAt,
            $this->lifetimeSeconds(),
        );

        if (! $wasChallengeStored) {
            throw new RuntimeException('Unable to issue signup challenge.');
        }

        return [
            'signupStartedAt' => $startedAt,
            'signupChallengeNonce' => $nonce,
            'signupChallengeToken' => $token,
        ];
    }

    /**
     * Validate and consume a submitted challenge, rejecting replays, fast posts, or stale submissions.
     */
    public function consumeIfValid(mixed $startedAt, mixed $nonce, mixed $token, ?CarbonImmutable $now = null): bool
    {
        if (! is_scalar($startedAt) || ! is_scalar($nonce) || ! is_scalar($token)) {
            return false;
        }

        $startedAt = trim((string) $startedAt);
        $nonce = trim((string) $nonce);
        $token = trim((string) $token);

        if (! ctype_digit($startedAt) || ! preg_match('/\A[a-f0-9]{32}\z/', $nonce) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }

        $currentTimestamp = ($now ?? CarbonImmutable::now('UTC'))->getTimestamp();
        $challengeAgeSeconds = $currentTimestamp - (int) $startedAt;

        if ($challengeAgeSeconds < $this->minimumAgeSeconds() || $challengeAgeSeconds > $this->lifetimeSeconds()) {
            return false;
        }

        if (! hash_equals($this->signature($startedAt, $nonce), $token)) {
            return false;
        }

        $cacheKey = $this->cacheKey($nonce);

        if ($this->cacheRepository->pull($cacheKey) !== $startedAt) {
            return false;
        }

        return true;
    }

    /**
     * Return the cache key used to track whether a challenge has already been consumed.
     */
    private function cacheKey(string $nonce): string
    {
        return self::CACHE_KEY_PREFIX.$nonce;
    }

    /**
     * Sign the challenge payload with the application key.
     */
    private function signature(string $startedAt, string $nonce): string
    {
        return hash_hmac('sha256', $startedAt.'|'.$nonce, $this->applicationKey());
    }

    /**
     * Resolve the signing key. Tests may run before a local key has been generated.
     */
    private function applicationKey(): string
    {
        $applicationKey = (string) config('app.key');

        if ($applicationKey !== '') {
            return $applicationKey;
        }

        if (app()->environment('testing')) {
            return 'hfm-local-assessment-key';
        }

        throw new RuntimeException('APP_KEY must be configured before issuing signup challenges.');
    }

    /**
     * Return the minimum number of seconds expected before a human submits the form.
     */
    private function minimumAgeSeconds(): int
    {
        return max(0, (int) config('features.signup_challenge_minimum_seconds', 2));
    }

    /**
     * Return the maximum challenge lifetime in seconds.
     */
    private function lifetimeSeconds(): int
    {
        return max(60, (int) config('features.signup_challenge_lifetime_seconds', 7200));
    }
}
