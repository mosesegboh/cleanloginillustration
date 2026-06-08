<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\SignupData;
use App\Models\Signup;

/**
 * Defines persistence operations needed by the signup and login flows.
 */
interface SignupRepository
{
    /**
     * Persist a normalized signup record.
     */
    public function create(SignupData $signupData): Signup;

    /**
     * Return the stored password hash for a signup email, if one exists.
     */
    public function passwordHashForEmail(string $email): ?string;
}
