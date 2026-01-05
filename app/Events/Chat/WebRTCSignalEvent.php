<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRTCSignalEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $callId;
    public int $senderId;
    public int $receiverId;
    public string $type;
    public mixed $data;

    public function __construct(int $callId, int $senderId, int $receiverId, string $type, mixed $data)
    {
        $this->callId = $callId;
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        $this->type = $type;
        $this->data = $data;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->receiverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc-signal';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'sender_id' => $this->senderId,
            'type' => $this->type, // 'offer', 'answer', 'ice-candidate'
            'data' => $this->data,
        ];
    }
}
