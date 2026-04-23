<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a tenant action would exceed its plan's quota
 * (work orders/month, customers, vehicles, users).
 *
 * Renders as HTTP 402 Payment Required with a redirect to the pricing page
 * for web requests, and a structured JSON body for API requests.
 */
class PlanQuotaExceededException extends Exception
{
    public function __construct(
        public readonly string $quota,
        public readonly ?int $limit = null,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Plan quota '{$quota}' reached. Upgrade to continue."
        );
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'plan_quota_exceeded',
                'quota' => $this->quota,
                'limit' => $this->limit,
                'message' => $this->getMessage(),
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        $message = $this->getMessage().' Please review your plan to continue.';

        return redirect()
            ->route('billing.pricing')
            ->with('error', $message);
    }
}
