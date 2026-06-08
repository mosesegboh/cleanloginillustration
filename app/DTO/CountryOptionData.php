<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Immutable country option used for backend validation and frontend select options.
 */
final readonly class CountryOptionData
{
    /**
     * @param  string  $value  ISO 3166-1 alpha-2 country code.
     * @param  string  $label  Human-readable country name.
     * @param  string  $countryCode  Numeric calling code without a leading plus sign.
     * @param  string  $dialingPrefix  Numeric calling code with a leading plus sign.
     * @param  string  $placeholder  Example national number for the selected country.
     */
    public function __construct(
        public string $value,
        public string $label,
        public string $countryCode,
        public string $dialingPrefix,
        public string $placeholder,
    ) {}

    /**
     * Convert the DTO to the shape consumed by the React application.
     *
     * @return array{value: string, label: string, countryCode: string, dialingPrefix: string, placeholder: string}
     */
    public function toFrontendArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'countryCode' => $this->countryCode,
            'dialingPrefix' => $this->dialingPrefix,
            'placeholder' => $this->placeholder,
        ];
    }
}
