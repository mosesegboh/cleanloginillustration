<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\CountryMetadataProvider;
use App\DTO\SignupData;
use App\Rules\HumanName;
use App\Support\Security\SignupSpamChallenge;
use Illuminate\Contracts\Validation\Validator as ValidationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Normalizes and validates signup submissions before they reach the controller.
 */
final class StoreSignupRequest extends FormRequest
{
    /**
     * Normalize scalar request values so validation and persistence use predictable types.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'firstName' => $this->trimmedStringInput('firstName'),
            'lastName' => $this->trimmedStringInput('lastName'),
            'country' => strtoupper($this->trimmedStringInput('country')),
            'countryCode' => $this->trimmedStringInput('countryCode'),
            'phoneNumber' => $this->trimmedStringInput('phoneNumber'),
            'email' => strtolower($this->trimmedStringInput('email')),
            'password' => $this->stringInput('password'),
            'acceptedTerms' => $this->booleanInput('acceptedTerms'),
            'companyWebsite' => $this->trimmedStringInput('companyWebsite'),
            'signupStartedAt' => $this->trimmedStringInput('signupStartedAt'),
            'signupChallengeNonce' => $this->trimmedStringInput('signupChallengeNonce'),
            'signupChallengeToken' => $this->trimmedStringInput('signupChallengeToken'),
        ]);
    }

    /**
     * Allow any client to submit signup requests.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for the registration form.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['bail', 'required', 'string', 'min:4', 'max:80', new HumanName('First name')],
            'lastName' => ['bail', 'required', 'string', 'min:4', 'max:80', new HumanName('Last name')],
            'country' => ['bail', 'required', 'string', 'size:2', Rule::in($this->countryMetadataProvider()->countryCodes())],
            'countryCode' => ['bail', 'required', 'string', 'regex:/^[0-9]+$/'],
            'phoneNumber' => ['bail', 'required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', 'unique:signups,email'],
            'acceptedTerms' => ['accepted'],
            'companyWebsite' => ['prohibited'],
            'signupStartedAt' => ['bail', 'required', 'integer'],
            'signupChallengeNonce' => ['bail', 'required', 'string', 'size:32'],
            'signupChallengeToken' => ['bail', 'required', 'string', 'size:64'],
            'password' => [
                'bail',
                'required',
                'string',
                'min:8',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /**
     * Add cross-field validation for the selected country and numeric calling code.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $countryOption = $this->countryMetadataProvider()->find((string) $this->input('country'));

            if ($countryOption === null || $validator->errors()->has('countryCode')) {
                return;
            }

            if ((string) $this->input('countryCode') !== $countryOption->countryCode) {
                $validator->errors()->add('countryCode', 'Country code must match the selected country.');
            }
        });

        $validator->after(function (Validator $validator): void {
            foreach (['signupStartedAt', 'signupChallengeNonce', 'signupChallengeToken'] as $challengeFieldName) {
                if ($validator->errors()->has($challengeFieldName)) {
                    return;
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->signupSpamChallenge()->consumeIfValid(
                $this->input('signupStartedAt'),
                $this->input('signupChallengeNonce'),
                $this->input('signupChallengeToken'),
            )) {
                $validator->errors()->add('signupChallengeToken', 'Please refresh the page and try again.');
            }
        });
    }

    /**
     * Return user-facing validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firstName.required' => 'Please enter your first name.',
            'firstName.min' => 'First name must be longer than 3 characters.',
            'lastName.required' => 'Please enter your last name.',
            'lastName.min' => 'Last name must be longer than 3 characters.',
            'country.required' => 'Please choose your country.',
            'country.size' => 'Please choose a valid country.',
            'country.in' => 'Please choose one of the supported countries.',
            'countryCode.required' => 'Please choose a country code.',
            'countryCode.regex' => 'Country code must contain numbers only.',
            'phoneNumber.required' => 'Please enter your phone number.',
            'phoneNumber.regex' => 'Phone number must contain 6 to 15 digits.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address has already signed up.',
            'acceptedTerms.accepted' => 'Please accept the Privacy Policy and Terms and Conditions.',
            'companyWebsite.prohibited' => 'Submission could not be accepted.',
            'signupStartedAt.required' => 'Please refresh the page and try again.',
            'signupStartedAt.integer' => 'Please refresh the page and try again.',
            'signupChallengeNonce.required' => 'Please refresh the page and try again.',
            'signupChallengeNonce.size' => 'Please refresh the page and try again.',
            'signupChallengeToken.required' => 'Please refresh the page and try again.',
            'signupChallengeToken.size' => 'Please refresh the page and try again.',
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }

    /**
     * Return JSON validation errors for browser fetch requests and API clients.
     */
    protected function failedValidation(ValidationContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first() ?: 'Please check the form and try again.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Convert the validated request into an immutable signup DTO.
     */
    public function toSignupData(): SignupData
    {
        $validatedData = $this->validated();

        return new SignupData(
            firstName: trim((string) $validatedData['firstName']),
            lastName: trim((string) $validatedData['lastName']),
            email: strtolower(trim((string) $validatedData['email'])),
            country: (string) $validatedData['country'],
            countryCode: (string) $validatedData['countryCode'],
            phoneNumber: (string) $validatedData['phoneNumber'],
            password: (string) $validatedData['password'],
        );
    }

    /**
     * Resolve the bound country metadata provider.
     */
    private function countryMetadataProvider(): CountryMetadataProvider
    {
        return app(CountryMetadataProvider::class);
    }

    /**
     * Resolve the sessionless signup anti-spam challenge validator.
     */
    private function signupSpamChallenge(): SignupSpamChallenge
    {
        return app(SignupSpamChallenge::class);
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

    /**
     * Return a boolean request input while rejecting arrays and objects before filtering.
     */
    private function booleanInput(string $fieldName): ?bool
    {
        $value = $this->input($fieldName);

        if (! is_scalar($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
