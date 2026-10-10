<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ensure customer-only API routes are used by active customer accounts. */
class EnsureCustomerRole
{
    use ApiResponseTrait;

    /** Reject inactive accounts and tokens issued to staff or administrator roles. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || $user->role !== 'customer') {
            return $this->errorResponse('An active customer account is required.', [], [], 403);
        }

        return $next($request);
    }
}
