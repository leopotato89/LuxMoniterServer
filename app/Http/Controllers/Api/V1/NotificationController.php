<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lịch sử thông báo của user — dựa trên Notification chuẩn của Laravel.
 */
class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(min(100, max(5, $request->integer('per_page', 20))));

        return response()->json([
            'data' => $notifications->getCollection()->map(fn ($notification) => [
                'id' => $notification->id,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                ...$notification->data,
            ])->values(),
            'unread_count' => $user->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['message' => 'Đã đánh dấu đã đọc.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Đã đánh dấu đọc tất cả.']);
    }
}
