<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // A user sees their own notifications plus every broadcast
        // (user_id null) -- see the notifications migration docblock.
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orWhereNull('user_id')
            ->latest('created_at')
            ->take(30)
            ->get();

        return response()->json(['data' => $notifications]);
    }

    public function markRead(Notification $notification)
    {
        $notification->update(['read' => true]);

        return response()->json(['data' => $notification]);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->orWhereNull('user_id')
            ->update(['read' => true]);

        return response()->json(status: 204);
    }
}
