@if(auth()->check() && auth()->user()->is_staff)
<!-- Toast Notification Container - Fixed at top right -->
<div id="chat-toast-container" class="fixed top-4 right-4 z-[200] flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

<!-- Notification Sound Element - Using Web Audio API for reliable playback -->

<div x-data="chatWidget()" x-init="init()" class="fixed bottom-6 left-6 z-50" @keydown.escape="closeChat()">
    <!-- Incoming Call Modal -->
    <div x-show="incomingCall" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center shadow-2xl max-w-sm w-full mx-4">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center animate-pulse">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2" x-text="incomingCall?.caller_name"></h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">تماس ورودی...</p>
            <div class="flex gap-4 justify-center">
                <button @click="rejectCall()" class="flex-1 py-3 px-6 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition">
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <button @click="answerCall()" class="flex-1 py-3 px-6 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition">
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Active Call UI -->
    <div x-show="activeCall" x-transition class="fixed bottom-24 left-6 bg-gray-900 text-white rounded-2xl p-4 shadow-2xl w-72">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-brand-500 flex items-center justify-center text-lg font-bold" x-text="activeCall?.remote_name?.charAt(0)"></div>
            <div>
                <p class="font-medium" x-text="activeCall?.remote_name"></p>
                <p class="text-sm text-gray-400" x-text="callDuration"></p>
            </div>
        </div>
        <div class="flex justify-center gap-4">
            <button @click="toggleMute()" :class="isMuted ? 'bg-red-500' : 'bg-gray-700'" class="p-3 rounded-full hover:opacity-80 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!isMuted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    <path x-show="isMuted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                </svg>
            </button>
            <button @click="endCall()" class="p-3 rounded-full bg-red-500 hover:bg-red-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Chat Panel -->
    <div x-show="isOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="absolute bottom-16 left-0 w-96 h-[32rem] bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-brand-500 text-white">
            <div class="flex items-center gap-3">
                <template x-if="currentView === 'conversations'">
                    <h3 class="font-bold">پیام‌ها</h3>
                </template>
                <template x-if="currentView === 'chat'">
                    <div class="flex items-center gap-2">
                        <button @click="currentView = 'conversations'" class="p-1 hover:bg-white/20 rounded">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <span class="font-bold" x-text="currentConversation?.display_name"></span>
                    </div>
                </template>
                <template x-if="currentView === 'users'">
                    <div class="flex items-center gap-2">
                        <button @click="currentView = 'conversations'" class="p-1 hover:bg-white/20 rounded">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <span class="font-bold">شروع گفتگو</span>
                    </div>
                </template>
                <template x-if="currentView === 'newGroup'">
                    <div class="flex items-center gap-2">
                        <button @click="currentView = 'conversations'" class="p-1 hover:bg-white/20 rounded">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <span class="font-bold">گروه جدید</span>
                    </div>
                </template>
            </div>
            <div class="flex items-center gap-2">
                <template x-if="currentView === 'conversations'">
                    <div class="flex gap-1">
                        <button @click="currentView = 'users'" class="p-2 hover:bg-white/20 rounded-lg" title="گفتگوی جدید">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <button @click="currentView = 'newGroup'" class="p-2 hover:bg-white/20 rounded-lg" title="گروه جدید">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                </template>
                <template x-if="currentView === 'chat'">
                    <button @click="initiateCall(currentConversation.user_id)" class="p-2 hover:bg-white/20 rounded-lg" title="تماس صوتی">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </button>
                </template>
                <button @click="closeChat()" class="p-2 hover:bg-white/20 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-hidden">
            <!-- Conversations List -->
            <div x-show="currentView === 'conversations'" class="h-full overflow-y-auto">
                <template x-if="conversations.length === 0">
                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                        <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <p class="text-sm">هنوز گفتگویی ندارید</p>
                        <button @click="currentView = 'users'" class="mt-2 text-brand-500 hover:underline text-sm">شروع گفتگو</button>
                    </div>
                </template>
                <template x-for="conv in conversations" :key="conv.id">
                    <div @click="openConversation(conv)" class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-brand-100 dark:bg-brand-900 flex items-center justify-center text-brand-600 dark:text-brand-400 font-bold" x-text="conv.display_name?.charAt(0)"></div>
                            <template x-if="conv.is_online">
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></span>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <h4 class="font-medium text-gray-900 dark:text-white truncate" x-text="conv.display_name"></h4>
                                <span class="text-xs text-gray-400" x-text="conv.last_message_time"></span>
                            </div>
                            <p class="text-sm text-gray-500 truncate" x-text="conv.last_message"></p>
                        </div>
                        <template x-if="conv.unread_count > 0">
                            <span class="bg-brand-500 text-white text-xs font-bold px-2 py-1 rounded-full" x-text="conv.unread_count"></span>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Users List -->
            <div x-show="currentView === 'users'" class="h-full overflow-y-auto">
                <template x-for="user in users" :key="user.id">
                    <div @click="startConversation(user.id)" class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-bold" x-text="user.name?.charAt(0)"></div>
                            <template x-if="user.is_online">
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></span>
                            </template>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white" x-text="user.name"></h4>
                            <p class="text-sm text-gray-500" x-text="user.role"></p>
                        </div>
                        <button @click.stop="initiateCall(user.id)" class="p-2 text-gray-400 hover:text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-900/20 rounded-lg" title="تماس">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </button>
                    </div>
                </template>
            </div>

            <!-- New Group -->
            <div x-show="currentView === 'newGroup'" class="h-full overflow-y-auto p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">نام گروه</label>
                    <input x-model="newGroupName" type="text" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-500 dark:bg-gray-700 dark:text-white" placeholder="نام گروه را وارد کنید">
                </div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">انتخاب اعضا</label>
                <template x-for="user in users" :key="user.id">
                    <label class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer rounded-lg">
                        <input type="checkbox" :value="user.id" x-model="selectedGroupMembers" class="w-4 h-4 text-brand-500 rounded">
                        <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-bold" x-text="user.name?.charAt(0)"></div>
                        <span class="text-gray-900 dark:text-white" x-text="user.name"></span>
                    </label>
                </template>
                <button @click="createGroup()" :disabled="!newGroupName || selectedGroupMembers.length === 0" class="mt-4 w-full py-3 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                    ایجاد گروه
                </button>
            </div>

            <!-- Chat View -->
            <div x-show="currentView === 'chat'" class="h-full flex flex-col">
                <!-- Messages -->
                <div x-ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
                    <template x-for="msg in messages" :key="msg.id">
                        <div :class="msg.is_mine ? 'flex justify-start' : 'flex justify-end'">
                            <div :class="msg.is_mine ? 'bg-brand-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'" class="max-w-[75%] rounded-2xl px-4 py-2">
                                <template x-if="currentConversation?.type === 'group' && !msg.is_mine">
                                    <p class="text-xs font-medium mb-1 opacity-75" x-text="msg.sender_name"></p>
                                </template>
                                <p class="text-sm" x-text="msg.content"></p>
                                <p class="text-xs mt-1 opacity-60" x-text="msg.time"></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="isTyping">
                        <div class="flex justify-end">
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl px-4 py-2">
                                <div class="flex gap-1">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Input -->
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <button class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </button>
                        <input x-model="newMessage" @keydown.enter="sendMessage()" type="text" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-full focus:ring-2 focus:ring-brand-500 dark:bg-gray-700 dark:text-white text-sm" placeholder="پیام خود را بنویسید...">
                        <button @click="sendMessage()" :disabled="!newMessage.trim()" class="p-2 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 text-white rounded-full transition">
                            <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Toggle Button -->
    <button @click="toggleChat()" class="relative flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white rounded-full shadow-lg transition-all hover:scale-105 px-4 py-3">
        <template x-if="totalUnread > 0">
            <span class="bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center animate-bounce" x-text="totalUnread"></span>
        </template>
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <!-- Pulse Ring Animation -->
        <template x-if="totalUnread > 0 && !isOpen">
            <span class="absolute inset-0 rounded-full bg-brand-400 animate-ping opacity-75"></span>
        </template>
    </button>

    
    <!-- Audio elements for WebRTC -->
    <audio x-ref="localAudio" muted></audio>
    <audio x-ref="remoteAudio" autoplay></audio>
    <audio x-ref="ringtone" loop src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleAN1qeNzAACy9l0AAMz/LxMl3P8MACX8/wAA"></audio>
</div>

<script>
function chatWidget() {
    return {
        isOpen: false,
        currentView: 'conversations',
        conversations: [],
        users: [],
        messages: [],
        currentConversation: null,
        newMessage: '',
        newGroupName: '',
        selectedGroupMembers: [],
        totalUnread: 0,
        isTyping: false,

        // Notification tracking
        lastKnownMessages: {},
        toastQueue: [],
        audioInitialized: false,

        // Call state
        incomingCall: null,
        activeCall: null,
        callDuration: '00:00',
        callTimer: null,
        isMuted: false,

        // WebRTC
        peerConnection: null,
        localStream: null,

        async init() {
            console.log('🚀 Chat widget initializing...');

            // Store initial message state to prevent notifications on first load
            await this.loadConversations();
            console.log('📋 Loaded conversations:', this.conversations.length);

            this.conversations.forEach(conv => {
                this.lastKnownMessages[conv.id] = {
                    lastMessageId: conv.last_message_id || 0,
                    unreadCount: conv.unread_count || 0
                };
                console.log(`📌 Initial state for conv ${conv.id}: msgId=${conv.last_message_id}, unread=${conv.unread_count}`);
            });

            await this.loadUsers();
            this.updatePresence('online');

            // Setup Echo listeners if available
            this.setupEchoListeners();

            // Initialize audio on first user interaction
            document.addEventListener('click', () => this.initAudio(), { once: true });
            document.addEventListener('keydown', () => this.initAudio(), { once: true });

            // Poll for new messages every 3 seconds
            setInterval(async () => {
                await this.checkForNewMessages();

                if (this.currentView === 'chat' && this.currentConversation) {
                    this.loadMessages(this.currentConversation.id);
                }
            }, 3000);

            // Update presence every 30 seconds
            setInterval(() => {
                this.updatePresence('online');
            }, 30000);

            console.log('✅ Chat widget initialized');
        },

        initAudio() {
            if (this.audioInitialized) return;
            // Initialize Web Audio API context on user interaction
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    const ctx = new AudioContext();
                    // Create and immediately stop a silent oscillator to unlock audio
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    gain.gain.value = 0;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.001);
                    this.audioInitialized = true;
                    console.log('Audio initialized successfully');
                }
            } catch (e) {
                console.log('Audio init error:', e);
            }
        },

        async checkForNewMessages() {
            const previousState = JSON.parse(JSON.stringify(this.lastKnownMessages));
            await this.loadConversations();

            // Check each conversation for new messages
            this.conversations.forEach(conv => {
                const oldState = previousState[conv.id];
                const newUnread = conv.unread_count || 0;
                const newLastMsgId = conv.last_message_id || 0;

                // Find if this is the currently open conversation
                const isCurrentConversation = this.currentConversation?.id === conv.id && this.currentView === 'chat';

                // Debug log
                console.log(`🔍 Conv ${conv.id} (${conv.display_name}): oldUnread=${oldState?.unreadCount}, newUnread=${newUnread}, oldMsgId=${oldState?.lastMessageId}, newMsgId=${newLastMsgId}`);

                // If there's a new message
                if (oldState) {
                    const hasNewMessage = newUnread > oldState.unreadCount ||
                                         (newLastMsgId > oldState.lastMessageId && newUnread > 0);

                    // Show notification if:
                    // 1. There's a new message, AND
                    // 2. Either chat widget is closed OR viewing a different conversation
                    const shouldNotify = hasNewMessage && (!this.isOpen || !isCurrentConversation);

                    console.log(`📊 hasNewMessage=${hasNewMessage}, isOpen=${this.isOpen}, isCurrentConv=${isCurrentConversation}, shouldNotify=${shouldNotify}`);

                    if (shouldNotify) {
                        console.log('🔔 Showing notification for:', conv.display_name);
                        this.showToastNotification({
                            conversationId: conv.id,
                            senderName: conv.display_name,
                            message: conv.last_message || 'پیام جدید',
                            avatar: conv.display_name?.charAt(0) || '?'
                        });
                    }
                } else {
                    console.log(`⚠️ No oldState for conv ${conv.id}, this is a new conversation`);
                    // New conversation detected - add to tracking
                    if (newUnread > 0 && !this.isOpen) {
                        console.log('🔔 New conversation with unread, showing notification');
                        this.showToastNotification({
                            conversationId: conv.id,
                            senderName: conv.display_name,
                            message: conv.last_message || 'پیام جدید',
                            avatar: conv.display_name?.charAt(0) || '?'
                        });
                    }
                }

                // Update tracking state
                this.lastKnownMessages[conv.id] = {
                    lastMessageId: newLastMsgId,
                    unreadCount: newUnread
                };
            });
        },

        playNotificationSound() {
            console.log('Playing notification sound...');
            try {
                // Use Web Audio API for reliable cross-browser sound
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) {
                    console.log('Web Audio API not supported');
                    return;
                }

                const audioContext = new AudioContext();

                // Resume context if suspended (browser policy)
                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }

                // Create a pleasant "ding" notification sound
                const playTone = (frequency, startTime, duration, volume = 0.5) => {
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();

                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);

                    oscillator.frequency.value = frequency;
                    oscillator.type = 'sine';

                    // Envelope: quick attack, gradual decay
                    const now = audioContext.currentTime + startTime;
                    gainNode.gain.setValueAtTime(0, now);
                    gainNode.gain.linearRampToValueAtTime(volume, now + 0.01);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, now + duration);

                    oscillator.start(now);
                    oscillator.stop(now + duration);
                };

                // Play a pleasant two-tone "ding-dong" sound
                playTone(830, 0, 0.2, 0.5);      // First ding
                playTone(1046, 0.15, 0.3, 0.4);  // Second ding (higher)

                console.log('Notification sound played successfully');
            } catch (e) {
                console.log('Audio playback error:', e);
            }
        },

        showToastNotification({ conversationId, senderName, message, avatar }) {
            console.log('🍞 Creating toast notification:', { conversationId, senderName, message });

            // Play notification sound
            this.playNotificationSound();

            // Create toast element
            const container = document.getElementById('chat-toast-container');
            if (!container) {
                console.error('❌ Toast container not found!');
                return;
            }
            console.log('✅ Toast container found');

            const toastId = 'toast-' + Date.now();
            const truncatedMessage = message.length > 50 ? message.substring(0, 50) + '...' : message;

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = 'pointer-events-auto bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-4 transform scale-95 opacity-0 transition-all duration-300 ease-out cursor-pointer hover:shadow-3xl hover:scale-[1.02]';
            toast.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-brand-500 flex items-center justify-center text-white font-bold text-lg">
                        ${avatar}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="font-bold text-gray-900 dark:text-white text-sm truncate">${senderName}</h4>
                            <button onclick="event.stopPropagation(); this.closest('.pointer-events-auto').remove();" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1 line-clamp-2">${truncatedMessage}</p>
                        <p class="text-brand-500 text-xs mt-2 font-medium">کلیک کنید برای مشاهده</p>
                    </div>
                </div>
            `;

            // Store conversation ID for click handler
            toast.dataset.conversationId = conversationId;

            // Click to open chat
            toast.addEventListener('click', () => {
                const conv = this.conversations.find(c => c.id == conversationId);
                if (conv) {
                    this.isOpen = true;
                    this.openConversation(conv);
                } else {
                    this.isOpen = true;
                    this.currentView = 'conversations';
                }
                toast.remove();
            });

            container.appendChild(toast);
            console.log('✅ Toast added to container');

            // Animate in
            requestAnimationFrame(() => {
                toast.classList.remove('scale-95', 'opacity-0');
                toast.classList.add('scale-100', 'opacity-100');
                console.log('✅ Toast animated in');
            });

            // Auto remove after 8 seconds
            setTimeout(() => {
                if (document.getElementById(toastId)) {
                    toast.classList.remove('scale-100', 'opacity-100');
                    toast.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 8000);
        },

        setupEchoListeners() {
            // Check if Echo is available
            if (typeof window.Echo === 'undefined') {
                console.log('Laravel Echo not available, using polling');
                return;
            }

            const userId = {{ auth()->id() }};

            // Listen for user-specific events (calls, etc.)
            window.Echo.private(`user.${userId}`)
                .listen('.incoming-call', (data) => {
                    console.log('Incoming call:', data);
                    this.incomingCall = data.call;
                    this.$refs.ringtone.play().catch(() => {});
                })
                .listen('.call-answered', (data) => {
                    console.log('Call answered:', data);
                    // Handle call answered event
                })
                .listen('.webrtc-signal', (data) => {
                    console.log('WebRTC signal:', data);
                    this.handleWebRTCSignal(data);
                });

            // Join presence channel to see who's online
            window.Echo.join('presence-chat')
                .here((users) => {
                    console.log('Users online:', users);
                    this.updateOnlineStatus(users);
                })
                .joining((user) => {
                    console.log('User joined:', user);
                    this.markUserOnline(user.id);
                })
                .leaving((user) => {
                    console.log('User left:', user);
                    this.markUserOffline(user.id);
                });
        },

        // Subscribe to conversation channel
        subscribeToConversation(conversationId) {
            if (typeof window.Echo === 'undefined') return;

            window.Echo.private(`conversation.${conversationId}`)
                .listen('.new-message', (data) => {
                    console.log('New message:', data);
                    if (this.currentConversation?.id === conversationId) {
                        // Add message if not already present
                        if (!this.messages.find(m => m.id === data.message.id)) {
                            this.messages.push({
                                id: data.message.id,
                                content: data.message.content,
                                type: data.message.type,
                                sender_name: data.message.sender_name,
                                is_mine: data.message.sender_id === {{ auth()->id() }},
                                time: data.message.time,
                            });
                            this.$nextTick(() => this.scrollToBottom());
                        }
                    }
                    // Reload conversations to update unread counts
                    this.loadConversations();
                });
        },

        updateOnlineStatus(users) {
            const onlineIds = users.map(u => u.id);
            this.users = this.users.map(u => ({
                ...u,
                is_online: onlineIds.includes(u.id)
            }));
            this.conversations = this.conversations.map(c => ({
                ...c,
                is_online: c.user_id && onlineIds.includes(c.user_id)
            }));
        },

        markUserOnline(userId) {
            this.users = this.users.map(u => u.id === userId ? {...u, is_online: true} : u);
            this.conversations = this.conversations.map(c => c.user_id === userId ? {...c, is_online: true} : c);
        },

        markUserOffline(userId) {
            this.users = this.users.map(u => u.id === userId ? {...u, is_online: false} : u);
            this.conversations = this.conversations.map(c => c.user_id === userId ? {...c, is_online: false} : c);
        },

        handleWebRTCSignal(data) {
            if (!this.peerConnection) return;

            if (data.type === 'offer') {
                this.peerConnection.setRemoteDescription(new RTCSessionDescription(data.data))
                    .then(() => this.peerConnection.createAnswer())
                    .then(answer => {
                        this.peerConnection.setLocalDescription(answer);
                        this.sendSignal('answer', data.sender_id, answer);
                    });
            } else if (data.type === 'answer') {
                this.peerConnection.setRemoteDescription(new RTCSessionDescription(data.data));
            } else if (data.type === 'ice-candidate') {
                this.peerConnection.addIceCandidate(new RTCIceCandidate(data.data));
            }
        },

        async sendSignal(type, receiverId, data) {
            try {
                await fetch('/admin/chat/signal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        call_id: this.activeCall?.id,
                        receiver_id: receiverId,
                        type: type,
                        data: data
                    })
                });
            } catch (e) {
                console.error('Error sending signal:', e);
            }
        },

        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.loadConversations();
            }
        },

        closeChat() {
            this.isOpen = false;
            this.currentView = 'conversations';
        },

        async loadConversations() {
            try {
                const response = await fetch('/admin/chat/conversations');
                const data = await response.json();
                this.conversations = data.conversations || [];
                this.totalUnread = this.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
            } catch (e) {
                console.error('Error loading conversations:', e);
            }
        },

        async loadUsers() {
            try {
                const response = await fetch('/admin/chat/users');
                const data = await response.json();
                this.users = data.users || [];
            } catch (e) {
                console.error('Error loading users:', e);
            }
        },

        async openConversation(conv) {
            this.currentConversation = conv;
            this.currentView = 'chat';
            await this.loadMessages(conv.id);
            this.subscribeToConversation(conv.id);
            this.$nextTick(() => {
                this.scrollToBottom();
            });
        },

        async loadMessages(conversationId) {
            try {
                const response = await fetch(`/admin/chat/conversations/${conversationId}/messages`);
                const data = await response.json();
                this.messages = data.messages || [];
                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            } catch (e) {
                console.error('Error loading messages:', e);
            }
        },

        async startConversation(userId) {
            try {
                const response = await fetch('/admin/chat/conversations/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ user_id: userId })
                });
                const data = await response.json();
                if (data.conversation) {
                    this.currentConversation = data.conversation;
                    this.currentView = 'chat';
                    await this.loadMessages(data.conversation.id);
                }
            } catch (e) {
                console.error('Error starting conversation:', e);
            }
        },

        async createGroup() {
            if (!this.newGroupName || this.selectedGroupMembers.length === 0) return;

            try {
                const response = await fetch('/admin/chat/conversations/group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        name: this.newGroupName,
                        member_ids: this.selectedGroupMembers
                    })
                });
                const data = await response.json();
                if (data.conversation) {
                    this.newGroupName = '';
                    this.selectedGroupMembers = [];
                    this.currentConversation = data.conversation;
                    this.currentView = 'chat';
                    await this.loadConversations();
                }
            } catch (e) {
                console.error('Error creating group:', e);
            }
        },

        async sendMessage() {
            if (!this.newMessage.trim() || !this.currentConversation) return;

            const message = this.newMessage;
            this.newMessage = '';

            try {
                const response = await fetch(`/admin/chat/conversations/${this.currentConversation.id}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ content: message, type: 'text' })
                });
                const data = await response.json();
                if (data.message) {
                    this.messages.push(data.message);
                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });
                }
            } catch (e) {
                console.error('Error sending message:', e);
            }
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        async updatePresence(status) {
            try {
                await fetch('/admin/chat/presence', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status })
                });
            } catch (e) {
                console.error('Error updating presence:', e);
            }
        },

        // Call functions
        async initiateCall(userId) {
            try {
                const response = await fetch('/admin/chat/calls/initiate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ receiver_id: userId, type: 'audio' })
                });
                const data = await response.json();
                if (data.call) {
                    this.activeCall = data.call;
                    await this.setupWebRTC(true);
                    this.startCallTimer();
                }
            } catch (e) {
                console.error('Error initiating call:', e);
            }
        },

        async answerCall() {
            if (!this.incomingCall) return;

            try {
                const response = await fetch(`/admin/chat/calls/${this.incomingCall.id}/answer`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (data.call) {
                    this.activeCall = data.call;
                    this.incomingCall = null;
                    this.$refs.ringtone.pause();
                    await this.setupWebRTC(false);
                    this.startCallTimer();
                }
            } catch (e) {
                console.error('Error answering call:', e);
            }
        },

        async rejectCall() {
            if (!this.incomingCall) return;

            try {
                await fetch(`/admin/chat/calls/${this.incomingCall.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                this.incomingCall = null;
                this.$refs.ringtone.pause();
            } catch (e) {
                console.error('Error rejecting call:', e);
            }
        },

        async endCall() {
            if (!this.activeCall) return;

            try {
                await fetch(`/admin/chat/calls/${this.activeCall.id}/end`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
            } catch (e) {
                console.error('Error ending call:', e);
            }

            this.cleanupCall();
        },

        cleanupCall() {
            if (this.peerConnection) {
                this.peerConnection.close();
                this.peerConnection = null;
            }
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => track.stop());
                this.localStream = null;
            }
            if (this.callTimer) {
                clearInterval(this.callTimer);
                this.callTimer = null;
            }
            this.activeCall = null;
            this.callDuration = '00:00';
            this.isMuted = false;
        },

        startCallTimer() {
            let seconds = 0;
            this.callTimer = setInterval(() => {
                seconds++;
                const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
                const secs = (seconds % 60).toString().padStart(2, '0');
                this.callDuration = `${mins}:${secs}`;
            }, 1000);
        },

        toggleMute() {
            this.isMuted = !this.isMuted;
            if (this.localStream) {
                this.localStream.getAudioTracks().forEach(track => {
                    track.enabled = !this.isMuted;
                });
            }
        },

        async setupWebRTC(isInitiator) {
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                this.$refs.localAudio.srcObject = this.localStream;

                this.peerConnection = new RTCPeerConnection({
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' }
                    ]
                });

                this.localStream.getTracks().forEach(track => {
                    this.peerConnection.addTrack(track, this.localStream);
                });

                this.peerConnection.ontrack = (event) => {
                    this.$refs.remoteAudio.srcObject = event.streams[0];
                };

                // Get the remote user ID for signaling
                const remoteUserId = isInitiator
                    ? this.currentConversation?.user_id
                    : this.incomingCall?.caller_id;

                this.peerConnection.onicecandidate = (event) => {
                    if (event.candidate && remoteUserId) {
                        this.sendSignal('ice-candidate', remoteUserId, event.candidate);
                    }
                };

                if (isInitiator && remoteUserId) {
                    const offer = await this.peerConnection.createOffer();
                    await this.peerConnection.setLocalDescription(offer);
                    this.sendSignal('offer', remoteUserId, offer);
                }
            } catch (e) {
                console.error('Error setting up WebRTC:', e);
                alert('خطا در دسترسی به میکروفون');
                this.cleanupCall();
            }
        }
    }
}
</script>
@endif
