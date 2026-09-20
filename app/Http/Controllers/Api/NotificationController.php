<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get list of notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $notifications = Notification::forUser($userId)
            ->latest()
            ->take(30)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'action_url' => $notification->action_url,
                    'is_read' => $notification->read_at !== null,
                    'read_at' => $notification->read_at?->toISOString(),
                    'created_at' => $notification->created_at->toISOString(),
                    'created_at_human' => $notification->created_at->diffForHumans(),
                ];
            });

        $unreadCount = Notification::forUser($userId)->unread()->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $unreadCount,
            'data' => $notifications,
        ]);
    }

    /**
     * Get unread count only.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $count = Notification::forUser($userId)->unread()->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id && $notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to notification'], 403);
        }

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => [
                'id' => $notification->id,
                'is_read' => true,
                'read_at' => $notification->read_at->toISOString(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        Notification::forUser($userId)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id && $notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted successfully',
        ]);
    }
}
