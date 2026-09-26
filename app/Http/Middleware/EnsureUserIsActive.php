<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            // Revoke current token so they are logged out
            $request->user()->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Tài khoản của bạn đã bị đình chỉ.',
            ], 403);
        }

        return $next($request);
    }
}
