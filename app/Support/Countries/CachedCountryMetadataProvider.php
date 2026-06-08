<?php

declare(strict_types=1);

namespace App\Support\Countries;

use App\Contracts\CountryMetadataProvider;
use App\DTO\CountryOptionData;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Caches country metadata while preserving a JSON-safe cache payload.
 */
final readonly class CachedCountryMetadataProvider implements CountryMetadataProvider
{
    private const CACHE_KEY = 'country-metadata:v2';

    private const CACHE_TAG = 'country-metadata';

    /**
     * Create the caching decorator around another country metadata provider.
     */
    public function __construct(
        private CountryMetadataProvider $countryMetadataProvider,
        private CacheRepository $cacheRepository,
    ) {}

    /**
     * Return cached country options hydrated back into DTOs.
     *
     * @return list<CountryOptionData>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $countryOption): CountryOptionData => new CountryOptionData(
                value: $countryOption['value'],
                label: $countryOption['label'],
                countryCode: $countryOption['countryCode'],
                dialingPrefix: $countryOption['dialingPrefix'],
                placeholder: $countryOption['placeholder'],
            ),
            $this->cachedFrontendOptions(),
        );
    }

    /**
     * Return cached supported ISO 3166-1 alpha-2 country codes.
     *
     * @return list<string>
     */
    public function countryCodes(): array
    {
        return array_map(
            static fn (CountryOptionData $countryOption): string => $countryOption->value,
            $this->all(),
        );
    }

    /**
     * Find one cached country option by ISO 3166-1 alpha-2 country code.
     */
    public function find(string $countryCode): ?CountryOptionData
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));

        foreach ($this->all() as $countryOption) {
            if ($countryOption->value === $normalizedCountryCode) {
                return $countryOption;
            }
        }

        return null;
    }

    /**
     * Resolve a cached numeric calling code for an ISO country code.
     */
    public function callingCodeFor(string $countryCode): ?string
    {
        return $this->find($countryCode)?->countryCode;
    }

    /**
     * Return cached metadata in the frontend hydration shape.
     *
     * @return list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>
     */
    public function toFrontendOptions(): array
    {
        return $this->cachedFrontendOptions();
    }

    /**
     * Read or write the frontend-safe country metadata payload.
     *
     * @return list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>
     */
    private function cachedFrontendOptions(): array
    {
        return $this->remember(
            fn (): array => $this->countryMetadataProvider->toFrontendOptions(),
        );
    }

    /**
     * Cache country metadata forever, using tags when the active cache store supports them.
     *
     * @param  Closure(): list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>  $callback
     * @return list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>
     */
    private function remember(Closure $callback): array
    {
        if ($this->cacheRepository->getStore() instanceof TaggableStore) {
            return $this->cacheRepository
                ->tags([self::CACHE_TAG])
                ->rememberForever(self::CACHE_KEY, $callback);
        }

        return $this->cacheRepository->rememberForever(self::CACHE_KEY, $callback);
    }
}
