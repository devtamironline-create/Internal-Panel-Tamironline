<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $since = $request->get('since');

        // If 'since' parameter is provided, only return new notifications
        $newNotifications = [];
        if ($since) {
            $sinceTime = Carbon::createFromTimestampMs($since);
            $newNotifications = $user->notifications()
                ->where('created_at', '>', $sinceTime)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->data['type'] ?? class_basename($notification->type),
                        'title' => $notification->data['title'] ?? 'اعلان جدید',
                        'body' => $notification->data['body'] ?? $notification->data['message'] ?? '',
                        'url' => $notification->data['url'] ?? '#',
                    ];
                });
        }

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? class_basename($notification->type),
                    'title' => $notification->data['title'] ?? 'اعلان جدید',
                    'message' => $notification->data['body'] ?? $notification->data['message'] ?? '',
                    'url' => $notification->data['url'] ?? '#',
                    'icon' => $notification->data['icon'] ?? 'bell',
                    'read' => $notification->read_at !== null,
                    'time' => $notification->created_at->diffForHumans(),
                ];
            });

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'new_notifications' => $newNotifications,
        ]);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->find($id);
        
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
