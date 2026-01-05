<?php

namespace App\Events\Chat;

use App\Models\Chat\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->body,
                'type' => $this->message->type,
                'sender_id' => $this->message->user_id,
                'sender_name' => $this->message->user->full_name,
                'time' => $this->message->created_at->format('H:i'),
                'conversation_id' => $this->message->conversation_id,
            ],
        ];
    }
}
