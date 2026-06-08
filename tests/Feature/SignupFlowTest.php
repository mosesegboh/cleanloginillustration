<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SignupRepository;
use App\DTO\SignupData;
use App\Models\Signup;
use App\Support\Security\SignupSpamChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PDOException;
use Tests\TestCase;

/**
 * Covers signup and login API behavior.
 */
final class SignupFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Assert invalid signup payloads return structured JSON validation errors.
     */
    public function test_signup_validation_returns_clear_json_errors(): void
    {
        $response = $this->postJson('/api/signups', [
            'firstName' => 'Mo',
            'lastName' => 'Eg',
            'country' => 'CY',
            'countryCode' => 'abc',
            'phoneNumber' => '12',
            'email' => 'invalid-email',
            'password' => 'weak',
            'acceptedTerms' => false,
        ] + $this->validSignupChallenge());

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'firstName',
                'lastName',
                'countryCode',
                'phoneNumber',
                'email',
                'password',
                'acceptedTerms',
            ]);
    }

    /**
     * Assert country and calling code mismatches are rejected server-side.
     */
    public function test_signup_requires_country_code_to_match_selected_country(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Moses',
            'lastName' => 'Egboh',
            'country' => 'CY',
            'countryCode' => '234',
            'phoneNumber' => '8012345678',
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
            'acceptedTerms' => true,
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['countryCode']);
    }

    /**
     * Assert signup persistence and login validation work without browser session state.
     */
    public function test_signup_persists_and_login_validates_credentials_without_session_state(): void
    {
        $signupResponse = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Moses',
            'lastName' => 'Egboh',
            'country' => 'ng',
            'countryCode' => '234',
            'phoneNumber' => '8012345678',
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
            'acceptedTerms' => true,
        ]));

        $signupResponse
            ->assertCreated()
            ->assertJson([
                'message' => 'Thank you for signing up, Moses.',
            ]);

        $this->assertDatabaseHas('signups', [
            'email' => 'moses@example.com',
            'country' => 'NG',
            'country_code' => '234',
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJson([
                'message' => 'Login details validated successfully.',
            ]);
    }

    /**
     * Assert the browser signup route persists through the CSRF-protected web route.
     */
    public function test_browser_signup_route_persists_valid_submissions(): void
    {
        $response = $this->postJson('/register', $this->validSignupPayload([
            'email' => 'browser-signup@example.com',
        ]), $this->csrfHeaders());

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Thank you for signing up, Moses.',
            ]);

        $this->assertDatabaseHas('signups', [
            'email' => 'browser-signup@example.com',
        ]);
    }

    /**
     * Assert the browser login route validates credentials through the CSRF-protected web route.
     */
    public function test_browser_login_route_validates_credentials(): void
    {
        Signup::query()->create([
            'first_name' => 'Moses',
            'last_name' => 'Egboh',
            'email' => 'browser-login@example.com',
            'country' => 'NG',
            'country_code' => '234',
            'phone_number' => '8012345678',
            'password' => Hash::make('Secure1!'),
        ]);

        $response = $this->postJson('/login', [
            'email' => 'browser-login@example.com',
            'password' => 'Secure1!',
        ], $this->csrfHeaders());

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Login details validated successfully.',
            ]);
    }

    /**
     * Assert login rejects credentials that do not match the stored signup record.
     */
    public function test_login_rejects_invalid_credentials(): void
    {
        Signup::query()->create([
            'first_name' => 'Moses',
            'last_name' => 'Egboh',
            'email' => 'moses@example.com',
            'country' => 'NG',
            'country_code' => '234',
            'phone_number' => '8012345678',
            'password' => Hash::make('Secure1!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'moses@example.com',
            'password' => 'WrongPassword1!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'The email or password provided does not match our records.');
    }

    /**
     * Assert non-scalar login inputs are rejected as validation errors instead of being cast unsafely.
     */
    public function test_login_rejects_non_scalar_inputs_without_server_errors(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => ['moses@example.com'],
            'password' => ['Secure1!'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Assert suspicious URL-like name content is rejected before persistence.
     */
    public function test_signup_rejects_suspicious_name_content(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'https//:.',
            'lastName' => 'Egboh',
            'email' => 'suspicious-name@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['firstName'])
            ->assertJsonPath(
                'errors.firstName.0',
                'First name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            );

        $this->assertDatabaseMissing('signups', [
            'email' => 'suspicious-name@example.com',
        ]);
    }

    /**
     * Assert the signup form still accepts common real-name punctuation.
     */
    public function test_signup_accepts_common_real_name_punctuation(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Anne-Marie',
            'lastName' => "O'Neil",
            'email' => 'punctuation@example.com',
        ]));

        $response->assertCreated();

        $this->assertDatabaseHas('signups', [
            'first_name' => 'Anne-Marie',
            'last_name' => "O'Neil",
            'email' => 'punctuation@example.com',
        ]);
    }

    /**
     * Assert non-scalar signup inputs are rejected as validation errors instead of being cast unsafely.
     */
    public function test_signup_rejects_non_scalar_inputs_without_server_errors(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => ['Moses'],
            'acceptedTerms' => ['true'],
            'email' => 'non-scalar-signup@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['firstName', 'acceptedTerms']);

        $this->assertDatabaseMissing('signups', [
            'email' => 'non-scalar-signup@example.com',
        ]);
    }

    /**
     * Assert bot-like submissions that fill the hidden honeypot are rejected.
     */
    public function test_signup_rejects_honeypot_submissions(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'companyWebsite' => 'https://spam.example',
            'email' => 'honeypot@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['companyWebsite']);
    }

    /**
     * Assert signup rejects missing or tampered anti-spam challenge data.
     */
    public function test_signup_rejects_invalid_spam_challenges(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'signupChallengeToken' => str_repeat('0', 64),
            'email' => 'invalid-challenge@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signupChallengeToken']);
    }

    /**
     * Assert ordinary validation errors do not consume an otherwise valid challenge.
     */
    public function test_signup_validation_errors_do_not_consume_spam_challenges(): void
    {
        $challenge = $this->validSignupChallenge();

        $this->postJson('/api/signups', $this->validSignupPayload([
            ...$challenge,
            'firstName' => 'https//:.',
            'email' => 'validation-before-challenge@example.com',
        ]))->assertUnprocessable();

        $this->postJson('/api/signups', $this->validSignupPayload([
            ...$challenge,
            'email' => 'challenge-still-valid@example.com',
        ]))->assertCreated();
    }

    /**
     * Assert a valid signup consumes its challenge so it cannot be replayed.
     */
    public function test_signup_rejects_reused_spam_challenges(): void
    {
        $challenge = $this->validSignupChallenge();

        $this->postJson('/api/signups', $this->validSignupPayload([
            ...$challenge,
            'email' => 'first-challenge-use@example.com',
        ]))->assertCreated();

        $this->postJson('/api/signups', $this->validSignupPayload([
            ...$challenge,
            'email' => 'second-challenge-use@example.com',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signupChallengeToken']);
    }

    /**
     * Assert signup rejects submissions that arrive too quickly for normal form interaction.
     */
    public function test_signup_rejects_unrealistically_fast_submissions(): void
    {
        $challenge = $this->signupSpamChallenge()->issue(CarbonImmutable::now('UTC'));

        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            ...$challenge,
            'email' => 'fast-submit@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signupChallengeToken']);
    }

    /**
     * Assert API clients can request a sessionless signup challenge without session cookies.
     */
    public function test_signup_challenge_endpoint_returns_stateless_json(): void
    {
        $response = $this->getJson('/api/signup-challenge');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'signupStartedAt',
                'signupChallengeNonce',
                'signupChallengeToken',
            ]);
    }

    /**
     * Assert repeated login attempts are throttled to reduce brute-force risk.
     */
    public function test_login_requests_are_rate_limited(): void
    {
        for ($attemptNumber = 1; $attemptNumber <= 5; $attemptNumber++) {
            $this->postJson('/api/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'WrongPassword1!',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'WrongPassword1!',
        ])->assertTooManyRequests();
    }

    /**
     * Assert repeated signup submissions are throttled to reduce spam risk.
     */
    public function test_signup_requests_are_rate_limited(): void
    {
        for ($attemptNumber = 1; $attemptNumber <= 10; $attemptNumber++) {
            $this->postJson('/api/signups', $this->validSignupPayload([
                'firstName' => 'Mo',
                'lastName' => 'Eg',
                'country' => 'XX',
                'countryCode' => 'abc',
                'phoneNumber' => '12',
                'email' => 'rate-limit@example.com',
                'password' => 'weak',
                'acceptedTerms' => false,
            ]))->assertUnprocessable();
        }

        $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Mo',
            'lastName' => 'Eg',
            'country' => 'XX',
            'countryCode' => 'abc',
            'phoneNumber' => '12',
            'email' => 'rate-limit@example.com',
            'password' => 'weak',
            'acceptedTerms' => false,
        ]))->assertTooManyRequests();
    }

    /**
     * Assert login can be disabled before request validation runs.
     */
    public function test_login_can_be_disabled_by_feature_flag(): void
    {
        config(['features.login_enabled' => false]);

        $response = $this->postJson('/api/login', [
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
        ]);

        $response
            ->assertServiceUnavailable()
            ->assertJson([
                'message' => 'Login validation is currently unavailable.',
            ]);
    }

    /**
     * Assert signup can be disabled before request validation runs.
     */
    public function test_signup_can_be_disabled_by_feature_flag(): void
    {
        config(['features.signup_enabled' => false]);

        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Moses',
            'lastName' => 'Egboh',
            'country' => 'NG',
            'countryCode' => '234',
            'phoneNumber' => '8012345678',
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
            'acceptedTerms' => true,
        ]));

        $response
            ->assertServiceUnavailable()
            ->assertJson([
                'message' => 'Signup is currently unavailable.',
            ]);
    }

    /**
     * Assert unsupported ISO country codes are rejected.
     */
    public function test_signup_rejects_unsupported_country_codes(): void
    {
        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Moses',
            'lastName' => 'Egboh',
            'country' => 'XX',
            'countryCode' => '999',
            'phoneNumber' => '8012345678',
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
            'acceptedTerms' => true,
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country']);
    }

    /**
     * Assert duplicate writes caused by concurrent requests return a validation-style response.
     */
    public function test_signup_handles_duplicate_email_insert_races(): void
    {
        $this->app->bind(SignupRepository::class, static fn (): SignupRepository => new class implements SignupRepository
        {
            /**
             * Simulate a duplicate email detected only at database insert time.
             */
            public function create(SignupData $signupData): Signup
            {
                throw new QueryException(
                    'sqlite',
                    'insert into "signups" ("email") values (?)',
                    [$signupData->email],
                    new PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: signups.email'),
                );
            }

            /**
             * Not used by this test.
             */
            public function passwordHashForEmail(string $email): ?string
            {
                return null;
            }
        });

        $response = $this->postJson('/api/signups', $this->validSignupPayload([
            'firstName' => 'Moses',
            'lastName' => 'Egboh',
            'country' => 'NG',
            'countryCode' => '234',
            'phoneNumber' => '8012345678',
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
            'acceptedTerms' => true,
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJson([
                'message' => 'This email address has already signed up.',
            ]);
    }

    /**
     * Return a complete valid signup payload, with optional field overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSignupPayload(array $overrides = []): array
    {
        return array_replace([
            'firstName' => 'Moses',
            'lastName' => 'Egboh',
            'country' => 'NG',
            'countryCode' => '234',
            'phoneNumber' => '8012345678',
            'email' => 'moses@example.com',
            'password' => 'Secure1!',
            'acceptedTerms' => true,
            'companyWebsite' => '',
        ], $this->validSignupChallenge(), $overrides);
    }

    /**
     * Return a valid anti-spam challenge old enough to pass human timing checks.
     *
     * @return array{signupStartedAt: string, signupChallengeNonce: string, signupChallengeToken: string}
     */
    private function validSignupChallenge(): array
    {
        return $this->signupSpamChallenge()->issue(CarbonImmutable::now('UTC')->subSeconds(3));
    }

    /**
     * Resolve the signup anti-spam challenge service.
     */
    private function signupSpamChallenge(): SignupSpamChallenge
    {
        return app(SignupSpamChallenge::class);
    }

    /**
     * Return headers and session state for CSRF-protected browser route tests.
     *
     * @return array<string, string>
     */
    private function csrfHeaders(): array
    {
        $csrfToken = 'test-csrf-token';

        $this->withSession([
            '_token' => $csrfToken,
        ]);

        return [
            'X-CSRF-TOKEN' => $csrfToken,
        ];
    }
}
