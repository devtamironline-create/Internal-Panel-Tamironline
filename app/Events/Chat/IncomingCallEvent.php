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

class IncomingCallEvent implements ShouldBroadcast
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
            new PrivateChannel('user.' . $this->call->receiver_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'incoming-call';
    }

    public function broadcastWith(): array
    {
        return [
            'call' => [
                'id' => $this->call->id,
                'caller_id' => $this->call->caller_id,
                'caller_name' => $this->call->caller->full_name,
                'type' => $this->call->type,
                'conversation_id' => $this->call->conversation_id,
            ],
        ];
    }
}
