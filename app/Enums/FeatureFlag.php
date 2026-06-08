<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lists runtime feature flags used to enable or disable public API flows.
 */
enum FeatureFlag: string
{
    case Signup = 'signup_enabled';
    case Login = 'login_enabled';

    /**
     * User-facing messages returned when a feature is disabled.
     *
     * @var array<string, string>
     */
    private const UNAVAILABLE_MESSAGES = [
        'signup_enabled' => 'Signup is currently unavailable.',
        'login_enabled' => 'Login validation is currently unavailable.',
    ];

    /**
     * Return whether the feature is enabled in config.
     */
    public function enabled(): bool
    {
        return (bool) config($this->configKey());
    }

    /**
     * Return the config key used by Laravel's config repository.
     */
    public function configKey(): string
    {
        return sprintf('features.%s', $this->value);
    }

    /**
     * Return the unavailable response message for this feature.
     */
    public function unavailableMessage(): string
    {
        return self::UNAVAILABLE_MESSAGES[$this->value];
    }
}
