<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Notification::with('product')
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = Notification::where('is_read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function clearAll(): JsonResponse
    {
        Notification::query()->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}