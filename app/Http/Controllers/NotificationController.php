<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Auth::user()->notifications()
            ->latest()
            ->limit(10)
            ->get(['id', 'data', 'read_at', 'created_at']);

        return response()->json(['notifications' => $notifications]);
    }

    public function markAsRead(DatabaseNotification $notification): JsonResponse
    {
        abort_unless(
            $notification->notifiable_id === Auth::id() && $notification->notifiable_type === Auth::user()->getMorphClass(),
            403,
        );

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function clearAll(): JsonResponse
    {
        Auth::user()->notifications()->delete();

        return response()->json(['success' => true]);
    }
}
