<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DeviceFcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Đăng ký / huỷ token FCM của điện thoại đang đăng nhập.
 */
class FcmTokenController
{
    /**
     * Một token chỉ thuộc một user: máy đổi tài khoản thì token chuyển sang người mới.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:ios,android'],
        ]);

        $token = DeviceFcmToken::query()->updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'device_name' => $data['device_name'] ?? null,
                'platform' => $data['platform'] ?? null,
            ],
        );

        return response()->json(['message' => 'Đã đăng ký token thông báo.', 'id' => $token->id], 201);
    }

    /**
     * Gọi khi đăng xuất để tài khoản cũ không còn nhận push trên máy này.
     */
    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        $request->user()->fcmTokens()->where('fcm_token', $data['fcm_token'])->delete();

        return response()->noContent();
    }
}
