<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * user_id null = a broadcast notification (surfaced to every user whose
     * role can see the relevant module, resolved client-side by the same
     * rule that already filters the sidebar). This mirrors the existing
     * frontend behaviour where pushNotification() in js/data.js had no
     * concept of a single recipient.
     */
    public static function push(string $message, ?int $userId = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'read' => false,
        ]);
    }
}
