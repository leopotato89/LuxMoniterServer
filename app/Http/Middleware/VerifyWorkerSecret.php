<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWorkerSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.worker.secret');

        if (empty($secret) || $request->header('X-Worker-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized Worker Webhook'], 403);
        }

        return $next($request);
    }
}
