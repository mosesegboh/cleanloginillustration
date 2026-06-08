<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\CountryOptionData;

/**
 * Provides normalized country and dialing-code metadata to the backend and frontend.
 */
interface CountryMetadataProvider
{
    /**
     * Return every supported country option.
     *
     * @return list<CountryOptionData>
     */
    public function all(): array;

    /**
     * Return supported ISO 3166-1 alpha-2 country codes.
     *
     * @return list<string>
     */
    public function countryCodes(): array;

    /**
     * Find one supported country option by ISO 3166-1 alpha-2 country code.
     */
    public function find(string $countryCode): ?CountryOptionData;

    /**
     * Resolve a country calling code by ISO 3166-1 alpha-2 country code.
     */
    public function callingCodeFor(string $countryCode): ?string;

    /**
     * Return metadata in a JSON-safe shape for React hydration.
     *
     * @return list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>
     */
    public function toFrontendOptions(): array;
}
