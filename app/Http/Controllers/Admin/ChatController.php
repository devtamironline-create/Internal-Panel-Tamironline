<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\Call;
use App\Models\Chat\UserPresence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    /**
     * Get list of staff users for chat
     */
    public function users(): JsonResponse
    {
        $users = User::where('is_staff', true)
            ->where('is_active', true)
            ->where('id', '!=', auth()->id())
            ->with('presence')
            ->get()
            ->map(function ($user) {
                $isOnline = $user->presence && $user->presence->status === 'online'
                    && $user->presence->last_seen_at?->diffInMinutes() < 5;
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'avatar' => mb_substr($user->first_name ?? 'U', 0, 1),
                    'role' => $user->roles->first()?->name ?? 'کاربر',
                    'is_online' => $isOnline,
                    'last_seen' => $user->presence?->last_seen_at?->diffForHumans(),
                ];
            });

        return response()->json(['users' => $users]);
    }

    /**
     * Get user's conversations
     */
    public function conversations(): JsonResponse
    {
        $userId = auth()->id();

        $conversations = Conversation::whereHas('participants', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('left_at');
            })
            ->with(['latestMessage.user', 'activeParticipants.presence'])
            ->get()
            ->map(function ($conversation) use ($userId) {
                $other = $conversation->getOtherParticipant($userId);
                $isOnline = false;
                if ($other && $other->presence) {
                    $isOnline = $other->presence->status === 'online'
                        && $other->presence->last_seen_at?->diffInMinutes() < 5;
                }
                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'display_name' => $conversation->getDisplayName($userId),
                    'user_id' => $other?->id,
                    'is_online' => $isOnline,
                    'unread_count' => $conversation->getUnreadCount($userId),
                    'last_message' => $conversation->latestMessage?->body ?? '',
                    'last_message_time' => $conversation->latestMessage?->created_at?->diffForHumans() ?? '',
                ];
            })
            ->sortByDesc(fn($c) => $c['last_message_time']);

        return response()->json(['conversations' => $conversations->values()]);
    }

    /**
     * Get or create private conversation with a user
     */
    public function startConversation(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation = Conversation::findOrCreatePrivate(
            auth()->id(),
            $request->user_id
        );

        $other = User::find($request->user_id);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => 'private',
                'display_name' => $other->full_name,
                'user_id' => $other->id,
            ],
        ]);
    }

    /**
     * Create a group conversation
     */
    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'exists:users,id',
            'settings' => 'nullable|array',
            'settings.onlyAdminsCanSend' => 'nullable|boolean',
            'settings.membersCanAddOthers' => 'nullable|boolean',
        ]);

        // Prepare settings
        $settings = [
            'only_admins_can_send' => $request->input('settings.onlyAdminsCanSend', false),
            'members_can_add_others' => $request->input('settings.membersCanAddOthers', true),
        ];

        $conversation = Conversation::createGroup(
            $request->name,
            auth()->id(),
            $request->member_ids,
            $request->description,
            $settings
        );

        // Set additional admins
        if ($request->filled('admin_ids')) {
            foreach ($request->admin_ids as $adminId) {
                $conversation->participants()->updateExistingPivot($adminId, [
                    'is_admin' => true,
                ]);
            }
        }

        // Create system message
        Message::createSystem(
            $conversation->id,
            'گروه «' . $request->name . '» ایجاد شد'
        );

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => 'group',
                'display_name' => $request->name,
            ],
        ]);
    }

    /**
     * Get messages for a conversation
     */
    public function messages(Conversation $conversation, Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Check if user is participant
        if (!$conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $messages = $conversation->messages()
            ->with('user', 'replyTo.user')
            ->latest()
            ->take($request->get('limit', 50))
            ->get()
            ->reverse()
            ->map(function ($message) use ($userId) {
                return [
                    'id' => $message->id,
                    'content' => $message->body,
                    'type' => $message->type,
                    'file_path' => $message->file_path,
                    'file_name' => $message->file_name,
                    'file_size' => $message->file_size,
                    'sender_name' => $message->user->full_name,
                    'is_mine' => $message->user_id === $userId,
                    'time' => $message->created_at->format('H:i'),
                ];
            });

        // Mark as read
        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);

        return response()->json(['messages' => $messages->values()]);
    }

    /**
     * Send a message
     */
    public function sendMessage(Conversation $conversation, Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Check if user is participant
        if (!$conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $request->validate([
            'content' => 'required_without:file|string|max:5000',
            'type' => 'nullable|in:text,file,image,audio',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        $type = $request->type ?? 'text';
        $filePath = null;
        $fileName = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('chat-files', 'public');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            $mimeType = $file->getMimeType();
            if (str_starts_with($mimeType, 'image/')) {
                $type = 'image';
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $type = 'audio';
            } else {
                $type = 'file';
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'body' => $request->content,
            'type' => $type,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
        ]);

        $message->load('user');

        return response()->json([
            'message' => [
                'id' => $message->id,
                'content' => $message->body,
                'type' => $message->type,
                'file_path' => $message->file_path,
                'file_name' => $message->file_name,
                'file_size' => $message->file_size,
                'sender_name' => $message->user->full_name,
                'is_mine' => true,
                'time' => $message->created_at->format('H:i'),
            ],
        ]);
    }

    /**
     * Update user presence
     */
    public function updatePresence(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:online,away,busy,offline',
            'page' => 'nullable|string',
        ]);

        $presence = UserPresence::setStatus(auth()->id(), $request->status);

        if ($request->page) {
            $presence->update(['current_page' => $request->page]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Set activity status (meeting, remote, lunch, break, etc.)
     */
    public function setActivityStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:online,meeting,remote,lunch,break,leave,busy,away',
        ]);

        $presence = UserPresence::setStatus(auth()->id(), $request->status);

        return response()->json([
            'success' => true,
            'status' => $presence->status,
            'label' => $presence->getStatusLabel(),
            'color' => $presence->getStatusColor(),
        ]);
    }

    /**
     * Get online users
     */
    public function onlineUsers(): JsonResponse
    {
        $onlineUsers = UserPresence::getOnlineUsers()
            ->filter(fn($p) => $p->user_id !== auth()->id())
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->full_name,
                'status' => $p->status,
            ]);

        return response()->json($onlineUsers->values());
    }

    /**
     * Initiate a call
     */
    public function initiateCall(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'type' => 'nullable|in:audio,video',
        ]);

        $receiver = User::find($request->receiver_id);
        $call = Call::initiate(auth()->id(), $request->receiver_id, $request->type ?? 'audio');

        // TODO: Broadcast call notification via WebSocket

        return response()->json([
            'call' => [
                'id' => $call->id,
                'conversation_id' => $call->conversation_id,
                'remote_name' => $receiver->full_name,
            ],
        ]);
    }

    /**
     * Answer a call
     */
    public function answerCall(Call $call): JsonResponse
    {
        if ($call->receiver_id !== auth()->id()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $call->answer();
        $call->load('caller');

        return response()->json([
            'call' => [
                'id' => $call->id,
                'conversation_id' => $call->conversation_id,
                'remote_name' => $call->caller->full_name,
            ],
        ]);
    }

    /**
     * End a call
     */
    public function endCall(Call $call): JsonResponse
    {
        if (!in_array(auth()->id(), [$call->caller_id, $call->receiver_id])) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $call->end();

        return response()->json([
            'status' => 'ended',
            'duration' => $call->getDurationFormatted(),
        ]);
    }

    /**
     * Reject a call
     */
    public function rejectCall(Call $call): JsonResponse
    {
        if ($call->receiver_id !== auth()->id()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $call->reject();

        return response()->json(['status' => 'rejected']);
    }

    /**
     * Get call history
     */
    public function callHistory(): JsonResponse
    {
        $userId = auth()->id();

        $calls = Call::where('caller_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['caller', 'receiver'])
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($call) use ($userId) {
                $isOutgoing = $call->caller_id === $userId;
                $other = $isOutgoing ? $call->receiver : $call->caller;

                return [
                    'id' => $call->id,
                    'type' => $isOutgoing ? 'outgoing' : 'incoming',
                    'status' => $call->status,
                    'status_label' => $call->getStatusLabel(),
                    'user' => [
                        'id' => $other->id,
                        'name' => $other->full_name,
                        'avatar' => mb_substr($other->first_name, 0, 1),
                    ],
                    'duration' => $call->getDurationFormatted(),
                    'time' => $call->created_at->diffForHumans(),
                ];
            });

        return response()->json($calls);
    }

    /**
     * Send WebRTC signaling data
     */
    public function sendSignal(Request $request): JsonResponse
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id',
            'receiver_id' => 'required|exists:users,id',
            'type' => 'required|in:offer,answer,ice-candidate',
            'data' => 'required',
        ]);

        $call = Call::find($request->call_id);

        // Verify user is part of the call
        if (!in_array(auth()->id(), [$call->caller_id, $call->receiver_id])) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        // Broadcast WebRTC signal
        event(new \App\Events\Chat\WebRTCSignalEvent(
            $request->call_id,
            auth()->id(),
            $request->receiver_id,
            $request->type,
            $request->data
        ));

        return response()->json(['status' => 'sent']);
    }

    /**
     * Check for incoming calls (polling)
     */
    public function checkIncomingCall(): JsonResponse
    {
        $userId = auth()->id();

        // Find ringing call where current user is receiver
        $call = Call::where('receiver_id', $userId)
            ->where('status', 'ringing')
            ->where('created_at', '>=', now()->subMinutes(1)) // Only calls in last minute
            ->with('caller')
            ->first();

        if ($call) {
            return response()->json([
                'has_call' => true,
                'call' => [
                    'id' => $call->id,
                    'caller_id' => $call->caller_id,
                    'caller_name' => $call->caller->full_name,
                    'type' => $call->type,
                    'conversation_id' => $call->conversation_id,
                ],
            ]);
        }

        return response()->json(['has_call' => false]);
    }

    /**
     * Get unread message count for notifications
     */
    public function getUnreadCount(): JsonResponse
    {
        $userId = auth()->id();

        $totalUnread = 0;
        $conversations = \App\Models\Chat\Conversation::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('left_at');
        })->get();

        foreach ($conversations as $conv) {
            $totalUnread += $conv->getUnreadCount($userId);
        }

        return response()->json([
            'unread_count' => $totalUnread,
        ]);
    }
}
