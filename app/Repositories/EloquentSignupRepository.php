<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\SignupRepository;
use App\DTO\SignupData;
use App\Models\Signup;
use Illuminate\Support\Facades\Hash;

/**
 * Eloquent-backed implementation of signup persistence operations.
 */
final class EloquentSignupRepository implements SignupRepository
{
    /**
     * Create a signup record with a hashed password.
     */
    public function create(SignupData $signupData): Signup
    {
        return Signup::query()->create([
            'first_name' => $signupData->firstName,
            'last_name' => $signupData->lastName,
            'email' => $signupData->email,
            'country' => $signupData->country,
            'country_code' => $signupData->countryCode,
            'phone_number' => $signupData->phoneNumber,
            'password' => Hash::make($signupData->password),
        ]);
    }

    /**
     * Fetch only the password hash needed for login validation.
     */
    public function passwordHashForEmail(string $email): ?string
    {
        $passwordHash = Signup::query()
            ->where('email', strtolower($email))
            ->value('password');

        return is_string($passwordHash) ? $passwordHash : null;
    }
}
