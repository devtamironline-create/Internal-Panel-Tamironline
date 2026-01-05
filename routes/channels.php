<?php

use App\Models\Chat\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for user-specific events (calls, notifications)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for conversation messages
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    return $conversation->participants()
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

// Presence channel for online users
Broadcast::channel('presence-chat', function ($user) {
    if ($user->is_staff) {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
        ];
    }
    return false;
});
