<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CountryMetadataProvider;
use Tests\TestCase;

/**
 * Covers country metadata generation and frontend hydration.
 */
final class CountryMetadataTest extends TestCase
{
    /**
     * Assert the provider exposes a broad country list and known calling codes.
     */
    public function test_country_provider_exposes_complete_supported_country_metadata(): void
    {
        $countryMetadataProvider = app(CountryMetadataProvider::class);

        $this->assertGreaterThan(200, count($countryMetadataProvider->all()));
        $this->assertSame('234', $countryMetadataProvider->callingCodeFor('ng'));
        $this->assertSame('44', $countryMetadataProvider->callingCodeFor('GB'));
        $this->assertSame('357', $countryMetadataProvider->callingCodeFor('CY'));
    }

    /**
     * Assert the registration page includes country metadata for the React form.
     */
    public function test_register_page_exposes_country_options_for_frontend(): void
    {
        $response = $this->get('/register');

        $response->assertOk();

        $countryOptions = $this->extractCountryOptions((string) $response->getContent());
        $countryOptionsByValue = collect($countryOptions)->keyBy('value');

        $this->assertGreaterThan(200, count($countryOptions));
        $this->assertSame('Nigeria', $countryOptionsByValue->get('NG')['label'] ?? null);
        $this->assertSame('234', $countryOptionsByValue->get('NG')['countryCode'] ?? null);
        $this->assertSame('+234', $countryOptionsByValue->get('NG')['dialingPrefix'] ?? null);
        $this->assertNotEmpty($countryOptionsByValue->get('GB')['placeholder'] ?? null);
        $this->assertNotEmpty($countryOptionsByValue->get('CY')['placeholder'] ?? null);
    }

    /**
     * Extract country options embedded in the Blade root element.
     *
     * @return list<array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}>
     */
    private function extractCountryOptions(string $html): array
    {
        $this->assertSame(1, preg_match("/data-country-options='([^']+)'/", $html, $matches));

        $decodedCountryOptions = json_decode(
            html_entity_decode($matches[1], ENT_QUOTES),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($decodedCountryOptions);

        return $decodedCountryOptions;
    }
}
