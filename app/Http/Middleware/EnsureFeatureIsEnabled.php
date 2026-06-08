<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks API requests for feature-flagged flows that are disabled at runtime.
 */
final class EnsureFeatureIsEnabled
{
    /**
     * Continue the request only when the configured feature flag is enabled.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $featureFlag): Response
    {
        $feature = FeatureFlag::from($featureFlag);

        if ($feature->enabled()) {
            return $next($request);
        }

        return response()->json([
            'message' => $feature->unavailableMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
