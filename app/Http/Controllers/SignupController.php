<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SignupRepository;
use App\Http\Requests\StoreSignupRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

/**
 * Handles JSON signup submissions from the React registration page and API clients.
 */
final class SignupController extends Controller
{
    /**
     * Create the controller with the signup persistence boundary.
     */
    public function __construct(
        private readonly SignupRepository $signupRepository,
    ) {}

    /**
     * Validate and persist a signup request, returning JSON for browser and terminal clients.
     */
    public function __invoke(StoreSignupRequest $request): JsonResponse
    {
        try {
            $signup = $this->signupRepository->create($request->toSignupData());
        } catch (QueryException $queryException) {
            if ($this->isUniqueConstraintViolation($queryException)) {
                return $this->duplicateEmailResponse();
            }

            throw $queryException;
        }

        return response()->json([
            'message' => sprintf('Thank you for signing up, %s.', $signup->first_name),
        ], 201);
    }

    /**
     * Detect duplicate signup writes that can occur when two valid requests race to insert the same email.
     */
    private function isUniqueConstraintViolation(QueryException $queryException): bool
    {
        $sqlState = (string) ($queryException->errorInfo[0] ?? $queryException->getCode());
        $driverErrorCode = (string) ($queryException->errorInfo[1] ?? '');
        $message = strtolower($queryException->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverErrorCode, ['19', '1062', '2067'], true)
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry');
    }

    /**
     * Return the duplicate email validation response used for validation and insert-race failures.
     */
    private function duplicateEmailResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This email address has already signed up.',
            'errors' => [
                'email' => ['This email address has already signed up.'],
            ],
        ], 422);
    }
}
