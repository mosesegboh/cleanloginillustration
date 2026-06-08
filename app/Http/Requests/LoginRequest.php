<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\SignupRepository;
use App\Rules\ValidSignupCredentials;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Normalizes and validates login credentials for JSON login requests.
 */
final class LoginRequest extends FormRequest
{
    /**
     * Normalize email and password inputs before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->trimmedStringInput('email')),
            'password' => $this->stringInput('password'),
        ]);
    }

    /**
     * Allow any client to submit login validation requests.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for login credentials.
     *
     * @return array<string, list<string|ValidSignupCredentials>>
     */
    public function rules(SignupRepository $signupRepository): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', new ValidSignupCredentials($signupRepository)],
            'password' => ['bail', 'required', 'string'],
        ];
    }

    /**
     * Return user-facing validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }

    /**
     * Return JSON validation errors for browser fetch requests and API clients.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first() ?: 'Please check the form and try again.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Return a scalar request input as a string, rejecting arrays and objects before casting.
     */
    private function stringInput(string $fieldName): string
    {
        $value = $this->input($fieldName);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Return a trimmed scalar request input as a string.
     */
    private function trimmedStringInput(string $fieldName): string
    {
        return trim($this->stringInput($fieldName));
    }
}
