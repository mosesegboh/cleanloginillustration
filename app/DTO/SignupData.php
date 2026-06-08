<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Immutable payload for a validated signup request.
 */
final readonly class SignupData
{
    /**
     * @param  string  $country  ISO 3166-1 alpha-2 country code.
     * @param  string  $countryCode  Numeric calling code without a leading plus sign.
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $country,
        public string $countryCode,
        public string $phoneNumber,
        public string $password,
    ) {}
}
