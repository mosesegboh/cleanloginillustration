<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Security\SignupSpamChallenge;
use Illuminate\Http\JsonResponse;

/**
 * Issues sessionless signup anti-spam challenges for browser and API clients.
 */
final class SignupChallengeController extends Controller
{
    /**
     * Return a signed challenge without creating server-side session state.
     */
    public function __invoke(SignupSpamChallenge $signupSpamChallenge): JsonResponse
    {
        return response()->json($signupSpamChallenge->issue());
    }
}
