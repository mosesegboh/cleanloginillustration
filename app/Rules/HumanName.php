<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects URLs, markup, digits, control characters, and other suspicious name input.
 */
final readonly class HumanName implements ValidationRule
{
    /**
     * Create the rule with the label used in validation feedback.
     */
    public function __construct(
        private string $fieldLabel,
    ) {}

    /**
     * Validate a human name while allowing common separators used in real names.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (preg_match('/\A[\p{L}\p{M}]+(?:[ .\'-][\p{L}\p{M}]+)*\z/u', $value) === 1) {
            return;
        }

        $fail(sprintf(
            '%s may only contain letters, spaces, hyphens, apostrophes, and periods.',
            $this->fieldLabel,
        ));
    }
}
