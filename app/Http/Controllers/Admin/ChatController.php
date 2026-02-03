<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageReaction;
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
                    'avatar' => $user->avatar_url,
                    'initials' => $user->initials,
                    'role' => $user->roles->first()?->name ?? 'کاربر',
                    'is_online' => $isOnline,
                    'status' => $user->getPresenceStatus(),
                    'status_label' => $user->getPresenceStatusLabel(),
                    'status_color' => $user->getPresenceStatusColor(),
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

        // Get user's conversations
        $userConversations = Conversation::whereHas('participants', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('left_at');
            })
            ->with(['latestMessage.user', 'activeParticipants.presence'])
            ->get();

        // Get public groups/channels that user is not a member of
        $publicGroups = Conversation::whereIn('type', ['group', 'channel'])
            ->whereJsonContains('settings->is_public', true)
            ->whereDoesntHave('participants', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('left_at');
            })
            ->with(['latestMessage.user'])
            ->get();

        $conversations = $userConversations->merge($publicGroups)
            ->map(function ($conversation) use ($userId) {
                $other = $conversation->getOtherParticipant($userId);
                $isOnline = false;
                $status = 'offline';
                $statusLabel = 'آفلاین';
                $statusColor = 'gray';

                // For private conversations
                if ($conversation->type === 'private' && $other && $other->presence) {
                    $isOnline = $other->presence->status === 'online'
                        && $other->presence->last_seen_at?->diffInMinutes() < 5;
                    $status = $other->getPresenceStatus();
                    $statusLabel = $other->getPresenceStatusLabel();
                    $statusColor = $other->getPresenceStatusColor();
                }

                // For groups, show member count as status
                if ($conversation->type === 'group') {
                    $memberCount = $conversation->activeParticipants()->count();
                    $statusLabel = $memberCount . ' عضو';
                    $statusColor = 'blue';

                    // Check if it's a public group user is not a member of
                    $isPublic = $conversation->settings['is_public'] ?? false;
                    $isMember = $conversation->participants()->where('user_id', $userId)->whereNull('left_at')->exists();

                    if ($isPublic && !$isMember) {
                        $statusLabel = 'گروه عمومی • ' . $memberCount . ' عضو';
                        $statusColor = 'green';
                    }
                }

                // For channels, show member count as status
                if ($conversation->type === 'channel') {
                    $memberCount = $conversation->activeParticipants()->count();
                    $statusLabel = $memberCount . ' عضو';
                    $statusColor = 'purple';

                    // Check if it's a public channel user is not a member of
                    $isPublic = $conversation->settings['is_public'] ?? false;
                    $isMember = $conversation->participants()->where('user_id', $userId)->whereNull('left_at')->exists();

                    if ($isPublic && !$isMember) {
                        $statusLabel = 'کانال عمومی • ' . $memberCount . ' عضو';
                        $statusColor = 'green';
                    }
                }

                // Group/Channel avatar
                $avatar = in_array($conversation->type, ['group', 'channel'])
                    ? ($conversation->avatar ? asset('storage/' . $conversation->avatar) : null)
                    : $other?->avatar_url;

                // Get personal pin status from cache or session
                $personalPins = session('personal_pins', []);

                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'display_name' => $conversation->getDisplayName($userId),
                    'user_id' => $other?->id,
                    'avatar' => $avatar,
                    'initials' => in_array($conversation->type, ['group', 'channel'])
                        ? mb_substr($conversation->name ?? 'گ', 0, 1)
                        : ($other?->initials ?? '؟'),
                    'is_online' => $isOnline,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'status_color' => $statusColor,
                    'unread_count' => $conversation->getUnreadCount($userId),
                    'last_message' => $conversation->latestMessage?->body ?? '',
                    'last_message_time' => $conversation->latestMessage?->created_at?->diffForHumans() ?? '',
                    'last_message_id' => $conversation->latestMessage?->id ?? 0,
                    'is_public' => $conversation->settings['is_public'] ?? false,
                    'is_member' => $conversation->participants()->where('user_id', $userId)->whereNull('left_at')->exists(),
                    'is_pinned_global' => $conversation->settings['is_pinned_global'] ?? false,
                    'is_pinned_personal' => in_array($conversation->id, $personalPins),
                    'member_ids' => $conversation->activeParticipants->pluck('id')->toArray(),
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

        $other = User::with('presence')->find($request->user_id);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => 'private',
                'display_name' => $other->full_name,
                'user_id' => $other->id,
                'avatar' => $other->avatar_url,
                'initials' => $other->initials,
                'is_online' => $other->isOnline(),
                'status' => $other->getPresenceStatus(),
                'status_label' => $other->getPresenceStatusLabel(),
                'status_color' => $other->getPresenceStatusColor(),
            ],
        ]);
    }

    /**
     * Create a group or channel conversation
     */
    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:group,channel',
            'member_ids' => 'nullable|string', // JSON string from FormData
            'admin_ids' => 'nullable|string', // JSON string from FormData
            'settings' => 'nullable|string', // JSON string from FormData
            'avatar' => 'nullable|image|max:2048', // 2MB max for avatar
        ]);

        // Parse JSON strings from FormData
        $type = $request->type ?? 'group';
        $memberIds = json_decode($request->member_ids ?? '[]', true) ?: [];
        $adminIds = json_decode($request->admin_ids ?? '[]', true) ?: [];
        $settingsData = json_decode($request->settings ?? '{}', true) ?: [];

        // Prepare settings
        $settings = [
            'is_public' => $settingsData['isPublic'] ?? false,
            'only_admins_can_send' => $type === 'channel' ? true : ($settingsData['onlyAdminsCanSend'] ?? false),
            'members_can_add_others' => $settingsData['membersCanAddOthers'] ?? true,
            'is_pinned_global' => $settingsData['isPinned'] ?? false,
        ];

        // If not public and no members selected
        if (!$settings['is_public'] && empty($memberIds)) {
            return response()->json(['error' => 'لطفا حداقل یک عضو انتخاب کنید یا گروه را عمومی کنید'], 422);
        }

        // Handle avatar upload
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('group-avatars', 'public');
        }

        $conversation = Conversation::createGroup(
            $request->name,
            auth()->id(),
            $memberIds,
            $request->description,
            $settings
        );

        // Update type to channel if specified
        if ($type === 'channel') {
            $conversation->update(['type' => 'channel']);
        }

        // Update avatar if uploaded
        if ($avatarPath) {
            $conversation->update(['avatar' => $avatarPath]);
        }

        // Set additional admins
        if (!empty($adminIds)) {
            foreach ($adminIds as $adminId) {
                if ($conversation->participants()->where('user_id', $adminId)->exists()) {
                    $conversation->participants()->updateExistingPivot($adminId, [
                        'is_admin' => true,
                    ]);
                }
            }
        }

        // Create system message
        $typeLabel = $type === 'channel' ? 'کانال' : 'گروه';
        Message::createSystem(
            $conversation->id,
            $typeLabel . ' «' . $request->name . '» ایجاد شد'
        );

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $type,
                'display_name' => $request->name,
                'avatar' => $avatarPath ? asset('storage/' . $avatarPath) : null,
                'is_public' => $settings['is_public'],
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

        // Get other participant IDs for checking read status
        $otherParticipantIds = $conversation->participants()
            ->where('user_id', '!=', $userId)
            ->whereNull('left_at')
            ->pluck('user_id')
            ->toArray();

        $messages = $conversation->messages()
            ->with(['user', 'replyTo.user', 'readBy', 'reactions.user'])
            ->latest()
            ->take($request->get('limit', 50))
            ->get()
            ->reverse()
            ->map(function ($message) use ($userId, $otherParticipantIds) {
                // Check if message is read by any other participant
                $isRead = false;
                if ($message->user_id === $userId) {
                    // For my messages, check if any other participant has read it
                    $readByIds = $message->readBy->pluck('id')->toArray();
                    $isRead = !empty(array_intersect($otherParticipantIds, $readByIds));
                }

                // Group reactions by emoji
                $reactions = $message->reactions->groupBy('emoji')->map(function ($group) use ($userId) {
                    return [
                        'emoji' => $group->first()->emoji,
                        'count' => $group->count(),
                        'users' => $group->map(fn($r) => [
                            'id' => $r->user_id,
                            'name' => $r->user->full_name,
                        ])->values(),
                        'has_reacted' => $group->contains('user_id', $userId),
                    ];
                })->values();

                // Reply info
                $replyTo = null;
                if ($message->replyTo) {
                    $replyTo = [
                        'id' => $message->replyTo->id,
                        'content' => mb_substr($message->replyTo->body ?? '', 0, 50) . (mb_strlen($message->replyTo->body ?? '') > 50 ? '...' : ''),
                        'sender_name' => $message->replyTo->user->full_name,
                        'is_mine' => $message->replyTo->user_id === $userId,
                    ];
                }

                return [
                    'id' => $message->id,
                    'content' => $message->body,
                    'type' => $message->type,
                    'file_path' => $message->file_path,
                    'file_name' => $message->file_name,
                    'file_size' => $message->file_size,
                    'sender_id' => $message->user_id,
                    'sender_name' => $message->user->full_name,
                    'sender_avatar' => $message->user->avatar_url,
                    'sender_initials' => $message->user->initials,
                    'is_mine' => $message->user_id === $userId,
                    'is_read' => $isRead,
                    'time' => $message->created_at->format('H:i'),
                    'reply_to' => $replyTo,
                    'reactions' => $reactions,
                ];
            });

        // Mark messages from others as read
        $messagesToMark = $conversation->messages()
            ->where('user_id', '!=', $userId)
            ->whereDoesntHave('readBy', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get();

        foreach ($messagesToMark as $message) {
            $message->markAsReadBy($userId);
        }

        // Update last_read_at for participant
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
            'content' => 'nullable|string|max:5000',
            'type' => 'nullable|in:text,file,image,audio',
            'file' => 'nullable|file|max:10240', // 10MB max
            'reply_to_id' => 'nullable|exists:messages,id',
            'forwarded_from' => 'nullable|integer',
            'file_path' => 'nullable|string', // For forwarded files
            'file_name' => 'nullable|string', // For forwarded files
        ]);

        $type = $request->type ?? 'text';
        $filePath = null;
        $fileName = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            // New file upload
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
        } elseif ($request->filled('file_path')) {
            // Forwarded file - reuse existing file path
            $filePath = $request->file_path;
            $fileName = $request->file_name;
            // Get file size from storage if exists
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
                $fileSize = \Illuminate\Support\Facades\Storage::disk('public')->size($filePath);
            }
        }

        // Build message data
        $messageData = [
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'body' => $request->content ?: ($request->filled('file_path') ? 'فوروارد شده' : ''),
            'type' => $type,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'reply_to_id' => $request->reply_to_id,
        ];

        // Only add forwarded_from if the column exists
        if (\Schema::hasColumn('messages', 'forwarded_from')) {
            $messageData['forwarded_from'] = $request->forwarded_from;
        }

        $message = Message::create($messageData);

        $message->load(['user', 'replyTo.user']);

        // Prepare reply_to info
        $replyTo = null;
        if ($message->replyTo) {
            $replyTo = [
                'id' => $message->replyTo->id,
                'content' => mb_substr($message->replyTo->body ?? '', 0, 50) . (mb_strlen($message->replyTo->body ?? '') > 50 ? '...' : ''),
                'sender_name' => $message->replyTo->user->full_name,
                'is_mine' => $message->replyTo->user_id === $userId,
            ];
        }

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
                'is_read' => false, // New message is not read yet
                'time' => $message->created_at->format('H:i'),
                'reply_to' => $replyTo,
                'reactions' => [],
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
     * Heartbeat - update last_seen_at without changing status
     */
    public function heartbeat(): JsonResponse
    {
        $presence = UserPresence::where('user_id', auth()->id())->first();

        if ($presence) {
            // Just update last_seen_at, keep current status
            $presence->update(['last_seen_at' => now()]);
        } else {
            // First time - set as online
            $presence = UserPresence::setStatus(auth()->id(), 'online');
        }

        return response()->json([
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

    /**
     * Add reaction to a message
     */
    public function addReaction(Message $message, Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Verify user has access to this conversation
        $conversation = $message->conversation;
        if (!$conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        // Add or update reaction
        $reaction = MessageReaction::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $userId,
                'emoji' => $request->emoji,
            ]
        );

        // Get updated reactions for this message
        $reactions = $this->getMessageReactions($message, $userId);

        return response()->json([
            'success' => true,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Remove reaction from a message
     */
    public function removeReaction(Message $message, Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Verify user has access to this conversation
        $conversation = $message->conversation;
        if (!$conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        // Remove reaction
        MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $userId,
            'emoji' => $request->emoji,
        ])->delete();

        // Get updated reactions for this message
        $reactions = $this->getMessageReactions($message, $userId);

        return response()->json([
            'success' => true,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Toggle reaction on a message
     */
    public function toggleReaction(Message $message, Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Verify user has access to this conversation
        $conversation = $message->conversation;
        if (!$conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        // Check if reaction exists
        $existing = MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $userId,
            'emoji' => $request->emoji,
        ])->first();

        if ($existing) {
            $existing->delete();
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $userId,
                'emoji' => $request->emoji,
            ]);
        }

        // Get updated reactions for this message
        $reactions = $this->getMessageReactions($message, $userId);

        return response()->json([
            'success' => true,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Helper: Get formatted reactions for a message
     */
    private function getMessageReactions(Message $message, int $userId): array
    {
        $message->load('reactions.user');

        return $message->reactions->groupBy('emoji')->map(function ($group) use ($userId) {
            return [
                'emoji' => $group->first()->emoji,
                'count' => $group->count(),
                'users' => $group->map(fn($r) => [
                    'id' => $r->user_id,
                    'name' => $r->user->full_name,
                ])->values(),
                'has_reacted' => $group->contains('user_id', $userId),
            ];
        })->values()->toArray();
    }

    /**
     * Join a public group
     */
    public function joinGroup(Conversation $conversation): JsonResponse
    {
        $userId = auth()->id();

        // Verify it's a public group or channel
        if (!in_array($conversation->type, ['group', 'channel']) || !($conversation->settings['is_public'] ?? false)) {
            return response()->json(['error' => 'این گروه/کانال عمومی نیست'], 403);
        }

        // Check if already a member
        if ($conversation->participants()->where('user_id', $userId)->whereNull('left_at')->exists()) {
            return response()->json(['error' => 'شما قبلا عضو هستید'], 422);
        }

        // Add user to group/channel
        $conversation->participants()->attach([
            $userId => ['joined_at' => now(), 'is_admin' => false]
        ]);

        // Create system message
        $user = User::find($userId);
        $typeLabel = $conversation->type === 'channel' ? 'کانال' : 'گروه';
        Message::createSystem(
            $conversation->id,
            $user->full_name . ' به ' . $typeLabel . ' پیوست'
        );

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'display_name' => $conversation->name,
                'avatar' => $conversation->avatar ? asset('storage/' . $conversation->avatar) : null,
            ],
        ]);
    }

    /**
     * Update a group or channel
     */
    public function updateGroup(Conversation $conversation, Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Verify user is admin of the group
        $participant = $conversation->participants()->where('user_id', $userId)->first();
        if (!$participant || !$participant->pivot->is_admin) {
            return response()->json(['error' => 'شما دسترسی به ویرایش این گروه ندارید'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'nullable|string',
            'member_ids' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $settingsData = json_decode($request->settings ?? '{}', true) ?: [];
        $memberIds = json_decode($request->member_ids ?? '[]', true) ?: [];

        // Update settings
        $settings = $conversation->settings ?? [];
        $settings['is_public'] = $settingsData['isPublic'] ?? ($settings['is_public'] ?? false);
        $settings['is_pinned_global'] = $settingsData['isPinned'] ?? ($settings['is_pinned_global'] ?? false);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($conversation->avatar) {
                \Storage::disk('public')->delete($conversation->avatar);
            }
            $avatarPath = $request->file('avatar')->store('group-avatars', 'public');
            $conversation->avatar = $avatarPath;
        }

        $conversation->name = $request->name;
        $conversation->settings = $settings;
        $conversation->save();

        // Update members
        if (!empty($memberIds)) {
            $currentMembers = $conversation->activeParticipants->pluck('id')->toArray();

            // Add new members
            foreach ($memberIds as $memberId) {
                if (!in_array($memberId, $currentMembers)) {
                    $conversation->participants()->attach([
                        $memberId => ['joined_at' => now(), 'is_admin' => false]
                    ]);
                }
            }

            // Remove members not in the list (except admins)
            foreach ($currentMembers as $currentMemberId) {
                if (!in_array($currentMemberId, $memberIds) && $currentMemberId !== $userId) {
                    $conversation->participants()->updateExistingPivot($currentMemberId, ['left_at' => now()]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'display_name' => $conversation->name,
                'avatar' => $conversation->avatar ? asset('storage/' . $conversation->avatar) : null,
            ],
        ]);
    }

    /**
     * Toggle personal pin for a conversation
     */
    public function togglePersonalPin(Conversation $conversation): JsonResponse
    {
        $personalPins = session('personal_pins', []);

        if (in_array($conversation->id, $personalPins)) {
            // Remove from pins
            $personalPins = array_filter($personalPins, fn($id) => $id !== $conversation->id);
        } else {
            // Check limit (max 3)
            if (count($personalPins) >= 3) {
                return response()->json(['error' => 'حداکثر 3 گفتگو می‌توانید پین کنید'], 422);
            }
            $personalPins[] = $conversation->id;
        }

        session(['personal_pins' => array_values($personalPins)]);

        return response()->json(['success' => true]);
    }

    /**
     * Toggle global pin for a group/channel (admin only)
     */
    public function toggleGlobalPin(Conversation $conversation): JsonResponse
    {
        $userId = auth()->id();

        // Verify user is admin of the group
        $participant = $conversation->participants()->where('user_id', $userId)->first();
        if (!$participant || !$participant->pivot->is_admin) {
            return response()->json(['error' => 'شما دسترسی به این عملیات ندارید'], 403);
        }

        $settings = $conversation->settings ?? [];
        $settings['is_pinned_global'] = !($settings['is_pinned_global'] ?? false);
        $conversation->settings = $settings;
        $conversation->save();

        return response()->json(['success' => true]);
    }
}
