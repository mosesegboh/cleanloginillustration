<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\SignupRepository;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Validates login credentials against stored signup records.
 */
final class ValidSignupCredentials implements DataAwareRule, ValidationRule
{
    /**
     * Request data supplied by Laravel before validation runs.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Create the rule with the persistence boundary used for credential lookup.
     */
    public function __construct(
        private readonly SignupRepository $signupRepository,
    ) {}

    /**
     * Store the full request payload so the email rule can compare the submitted password.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Validate the submitted email and password combination.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $password = $this->data['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return;
        }

        $passwordHash = $this->signupRepository->passwordHashForEmail(strtolower($value));

        if ($passwordHash === null || ! Hash::check($password, $passwordHash)) {
            $fail('The email or password provided does not match our records.');
        }
    }
}
