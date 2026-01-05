<?php

namespace App\Events\Chat;

use App\Models\Chat\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallAnsweredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Call $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->call->caller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call-answered';
    }

    public function broadcastWith(): array
    {
        return [
            'call' => [
                'id' => $this->call->id,
                'conversation_id' => $this->call->conversation_id,
            ],
        ];
    }
}
