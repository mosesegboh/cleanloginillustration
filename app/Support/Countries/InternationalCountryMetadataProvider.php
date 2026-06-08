<?php

declare(strict_types=1);

namespace App\Support\Countries;

use App\Contracts\CountryMetadataProvider;
use App\DTO\CountryOptionData;
use Giggsey\Locale\Locale;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Builds country and dialing-code metadata from libphonenumber region data.
 */
final class InternationalCountryMetadataProvider implements CountryMetadataProvider
{
    /**
     * @var list<CountryOptionData>|null
     */
    private ?array $countryOptions = null;

    /**
     * Return all supported country options sorted by country label.
     *
     * @return list<CountryOptionData>
     */
    public function all(): array
    {
        if ($this->countryOptions !== null) {
            return $this->countryOptions;
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $countryOptions = [];

        foreach ($phoneNumberUtil->getSupportedRegions() as $regionCode) {
            $countryCode = strtoupper($regionCode);
            $callingCode = $phoneNumberUtil->getCountryCodeForRegion($countryCode);

            if ($callingCode <= 0) {
                continue;
            }

            $label = Locale::getDisplayRegion('en-'.$countryCode, 'en');

            if ($label === '') {
                continue;
            }

            $countryOptions[] = new CountryOptionData(
                value: $countryCode,
                label: $label,
                countryCode: (string) $callingCode,
                dialingPrefix: '+'.$callingCode,
                placeholder: $this->exampleNationalNumber($phoneNumberUtil, $countryCode),
            );
        }

        usort(
            $countryOptions,
            static fn (CountryOptionData $firstCountryOption, CountryOptionData $secondCountryOption): int => strnatcasecmp(
                $firstCountryOption->label,
                $secondCountryOption->label,
            ),
        );

        return $this->countryOptions = $countryOptions;
    }

    /**
     * Return supported ISO 3166-1 alpha-2 country codes.
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
     * Find one generated country option by ISO 3166-1 alpha-2 country code.
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
     * Resolve a numeric calling code for an ISO country code.
     */
    public function callingCodeFor(string $countryCode): ?string
    {
        return $this->find($countryCode)?->countryCode;
    }

    /**
     * Return generated metadata in the JSON-safe frontend hydration shape.
     *
     * @return list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>
     */
    public function toFrontendOptions(): array
    {
        return array_map(
            static fn (CountryOptionData $countryOption): array => $countryOption->toFrontendArray(),
            $this->all(),
        );
    }

    /**
     * Resolve an example national phone number for a country, preferring mobile examples.
     */
    private function exampleNationalNumber(PhoneNumberUtil $phoneNumberUtil, string $countryCode): string
    {
        $exampleNumber = $phoneNumberUtil->getExampleNumberForType($countryCode, PhoneNumberType::MOBILE)
            ?? $phoneNumberUtil->getExampleNumber($countryCode);

        $nationalNumber = $exampleNumber?->getNationalNumber();

        return is_scalar($nationalNumber) ? (string) $nationalNumber : '';
    }
}
