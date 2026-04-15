<?php

namespace App\Notifications;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Message $message,
        public Conversation $conversation,
        public string $senderName,
        public string $preview
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'mention',
            'message_id' => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'conversation_name' => $this->conversation->name ?? 'چت',
            'sender_name' => $this->senderName,
            'message' => "{$this->senderName} شما را در «{$this->conversation->name}» منشن کرد: {$this->preview}",
            'url' => route('admin.messenger') . '?conversation=' . $this->conversation->id,
        ];
    }
}
