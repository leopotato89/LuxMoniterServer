<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ép mọi request thuộc nhóm `api` được coi là mong đợi JSON.
 *
 * Không có middleware này, Laravel quyết định kiểu phản hồi lỗi dựa vào header `Accept`
 * của client: thiếu `Accept: application/json` thì lỗi xác thực sẽ cố redirect tới
 * route `login` (không tồn tại trong app API-only) → 500, và lỗi validation trả 302
 * kèm redirect thay vì 422. SPA, mobile và curl đều phải nhận cùng một hợp đồng JSON.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
