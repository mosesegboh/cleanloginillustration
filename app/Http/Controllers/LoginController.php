<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;

/**
 * Returns a successful response after login credentials have been validated.
 */
final class LoginController extends Controller
{
    /**
     * Handle a JSON login validation request.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $request->validated();

        return response()->json([
            'message' => 'Login details validated successfully.',
        ]);
    }
}
