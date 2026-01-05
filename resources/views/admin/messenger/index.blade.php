@extends('layouts.admin')

@section('page-title', 'پیام‌رسان')

@section('main')
<div x-data="messenger()" x-init="init()" class="h-[calc(100vh-140px)] flex bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">

    <!-- Sidebar - Conversations List -->
    <div class="w-80 border-l border-gray-200 dark:border-gray-700 flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">پیام‌ها</h2>
                <div class="flex gap-2">
                    <button @click="showNewGroup = true" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg" title="گروه جدید">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                    <button @click="showPhone = true" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg" title="تماس">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </button>
                </div>
            </div>
            <!-- Search -->
            <div class="relative">
                <input type="text" x-model="searchQuery" placeholder="جستجو..." class="w-full px-4 py-2 pr-10 border border-gray-200 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                <svg class="w-5 h-5 absolute right-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <!-- Users / New Chat -->
        <div x-show="showUsers" class="flex-1 overflow-y-auto">
            <div class="p-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <button @click="showUsers = false" class="text-sm text-brand-500 hover:underline flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    بازگشت به گفتگوها
                </button>
            </div>
            <template x-for="user in filteredUsers" :key="user.id">
                <div @click="startConversation(user.id)" class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full bg-brand-100 dark:bg-brand-900 flex items-center justify-center text-brand-600 dark:text-brand-400 font-bold text-lg" x-text="user.name?.charAt(0)"></div>
                        <span x-show="user.is_online" class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></span>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 dark:text-white" x-text="user.name"></h4>
                        <p class="text-sm text-gray-500" x-text="user.role || 'کاربر'"></p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Conversations List -->
        <div x-show="!showUsers" class="flex-1 overflow-y-auto">
            <button @click="showUsers = true" class="w-full p-4 text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-900/20 flex items-center gap-3 border-b border-gray-200 dark:border-gray-700">
                <div class="w-12 h-12 rounded-full bg-brand-500 flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <span class="font-medium">گفتگوی جدید</span>
            </button>

            <template x-if="conversations.length === 0">
                <div class="p-8 text-center text-gray-400">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <p>هنوز گفتگویی ندارید</p>
                </div>
            </template>

            <template x-for="conv in filteredConversations" :key="conv.id">
                <div @click="openConversation(conv)" :class="currentConversation?.id === conv.id ? 'bg-brand-50 dark:bg-brand-900/20 border-r-4 border-brand-500' : 'hover:bg-gray-50 dark:hover:bg-gray-700'" class="flex items-center gap-3 p-4 cursor-pointer border-b border-gray-100 dark:border-gray-700">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-bold text-lg" x-text="conv.display_name?.charAt(0)"></div>
                        <span x-show="conv.is_online" class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-gray-900 dark:text-white truncate" x-text="conv.display_name"></h4>
                            <span class="text-xs text-gray-400" x-text="conv.last_message_time"></span>
                        </div>
                        <p class="text-sm text-gray-500 truncate" x-text="conv.last_message || 'شروع گفتگو...'"></p>
                    </div>
                    <template x-if="conv.unread_count > 0">
                        <span class="bg-brand-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[24px] text-center" x-text="conv.unread_count"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        <!-- No Chat Selected -->
        <template x-if="!currentConversation">
            <div class="flex-1 flex items-center justify-center bg-gray-50 dark:bg-gray-900">
                <div class="text-center text-gray-400">
                    <svg class="w-24 h-24 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <p class="text-lg">یک گفتگو انتخاب کنید</p>
                    <p class="text-sm mt-2">یا گفتگوی جدید شروع کنید</p>
                </div>
            </div>
        </template>

        <!-- Chat Header -->
        <template x-if="currentConversation">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900 flex items-center justify-center text-brand-600 dark:text-brand-400 font-bold" x-text="currentConversation?.display_name?.charAt(0)"></div>
                        <span x-show="currentConversation?.is_online" class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white" x-text="currentConversation?.display_name"></h3>
                        <p class="text-sm text-gray-500" x-text="currentConversation?.is_online ? 'آنلاین' : 'آفلاین'"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="initiateCall(currentConversation?.user_id)" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg" title="تماس صوتی">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </button>
                </div>
            </div>
        </template>

        <!-- Messages Area -->
        <template x-if="currentConversation">
            <div x-ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50 dark:bg-gray-900">
                <template x-for="msg in messages" :key="msg.id">
                    <div :class="msg.is_mine ? 'flex justify-start' : 'flex justify-end'">
                        <div :class="msg.is_mine ? 'bg-brand-500 text-white rounded-tl-none' : 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-tr-none shadow'" class="max-w-md rounded-2xl px-4 py-3">
                            <template x-if="currentConversation?.type === 'group' && !msg.is_mine">
                                <p class="text-xs font-medium mb-1 opacity-75" x-text="msg.sender_name"></p>
                            </template>
                            <p class="text-sm leading-relaxed" x-text="msg.content"></p>
                            <p class="text-xs mt-1 opacity-60 text-left" x-text="msg.time"></p>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Message Input -->
        <template x-if="currentConversation">
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <button class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    <input x-model="newMessage" @keydown.enter="sendMessage()" type="text" class="flex-1 px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500" placeholder="پیام خود را بنویسید...">
                    <button @click="sendMessage()" :disabled="!newMessage.trim()" class="p-3 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 text-white rounded-xl transition">
                        <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Phone Modal -->
    <div x-show="showPhone" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPhone = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-96 shadow-2xl overflow-hidden">
            <div class="bg-brand-500 text-white p-6 text-center">
                <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <h3 class="text-xl font-bold">تماس صوتی</h3>
                <p class="text-sm opacity-80">یک کاربر برای تماس انتخاب کنید</p>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <template x-for="user in users" :key="user.id">
                    <div @click="initiateCall(user.id); showPhone = false" class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-bold" x-text="user.name?.charAt(0)"></div>
                            <span x-show="user.is_online" class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></span>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white" x-text="user.name"></h4>
                            <p class="text-sm" :class="user.is_online ? 'text-green-500' : 'text-gray-400'" x-text="user.is_online ? 'آنلاین' : 'آفلاین'"></p>
                        </div>
                        <div class="p-2 bg-green-500 text-white rounded-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                    </div>
                </template>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <button @click="showPhone = false" class="w-full py-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">انصراف</button>
            </div>
        </div>
    </div>

    <!-- New Group Modal -->
    <div x-show="showNewGroup" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showNewGroup = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-96 shadow-2xl overflow-hidden">
            <div class="bg-brand-500 text-white p-6 text-center">
                <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <h3 class="text-xl font-bold">گروه جدید</h3>
            </div>
            <div class="p-4">
                <input x-model="newGroupName" type="text" class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="نام گروه">
            </div>
            <div class="max-h-60 overflow-y-auto px-4">
                <template x-for="user in users" :key="user.id">
                    <label class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer rounded-lg">
                        <input type="checkbox" :value="user.id" x-model="selectedGroupMembers" class="w-5 h-5 text-brand-500 rounded">
                        <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center font-bold" x-text="user.name?.charAt(0)"></div>
                        <span class="text-gray-900 dark:text-white" x-text="user.name"></span>
                    </label>
                </template>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
                <button @click="showNewGroup = false" class="flex-1 py-3 text-gray-500 hover:text-gray-700 dark:text-gray-400">انصراف</button>
                <button @click="createGroup()" :disabled="!newGroupName || selectedGroupMembers.length === 0" class="flex-1 py-3 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 text-white rounded-lg">ایجاد</button>
            </div>
        </div>
    </div>

    <!-- Incoming Call Modal -->
    <div x-show="incomingCall" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center shadow-2xl max-w-sm w-full mx-4">
            <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center animate-pulse">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2" x-text="incomingCall?.caller_name"></h3>
            <p class="text-gray-500 dark:text-gray-400 mb-8">تماس ورودی...</p>
            <div class="flex gap-6 justify-center">
                <button @click="rejectCall()" class="w-16 h-16 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition transform hover:scale-105">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <button @click="answerCall()" class="w-16 h-16 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center transition transform hover:scale-105 animate-bounce">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Active Call Modal -->
    <div x-show="activeCall" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800">
        <div class="text-center text-white">
            <div class="w-32 h-32 mx-auto mb-6 rounded-full bg-white/20 flex items-center justify-center text-5xl font-bold" x-text="activeCall?.remote_name?.charAt(0)"></div>
            <h3 class="text-3xl font-bold mb-2" x-text="activeCall?.remote_name"></h3>
            <p class="text-xl opacity-80 mb-8" x-text="callDuration"></p>
            <div class="flex gap-6 justify-center">
                <button @click="toggleMute()" :class="isMuted ? 'bg-red-500' : 'bg-white/20'" class="w-14 h-14 rounded-full flex items-center justify-center transition hover:opacity-80">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!isMuted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        <path x-show="isMuted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                    </svg>
                </button>
                <button @click="endCall()" class="w-14 h-14 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Audio Elements -->
    <audio x-ref="localAudio" muted></audio>
    <audio x-ref="remoteAudio" autoplay></audio>
    <audio x-ref="ringtone" loop>
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleAN1qeNzAACy9l0AAMz/LxMl3P8MACX8/wAA" type="audio/wav">
    </audio>
</div>

<script>
function messenger() {
    return {
        conversations: [],
        users: [],
        messages: [],
        currentConversation: null,
        newMessage: '',
        searchQuery: '',
        showUsers: false,
        showPhone: false,
        showNewGroup: false,
        newGroupName: '',
        selectedGroupMembers: [],

        // Call state
        incomingCall: null,
        activeCall: null,
        callDuration: '00:00',
        callTimer: null,
        isMuted: false,
        peerConnection: null,
        localStream: null,

        get filteredConversations() {
            if (!this.searchQuery) return this.conversations;
            return this.conversations.filter(c =>
                c.display_name?.toLowerCase().includes(this.searchQuery.toLowerCase())
            );
        },

        get filteredUsers() {
            if (!this.searchQuery) return this.users;
            return this.users.filter(u =>
                u.name?.toLowerCase().includes(this.searchQuery.toLowerCase())
            );
        },

        async init() {
            await this.loadConversations();
            await this.loadUsers();
            this.updatePresence('online');

            // Request notification permission
            this.requestNotificationPermission();

            // Poll for updates every 2 seconds
            setInterval(async () => {
                const oldUnread = this.getTotalUnread();
                await this.loadConversations();

                // Check for new messages
                const newUnread = this.getTotalUnread();
                if (newUnread > oldUnread) {
                    this.showNotification('پیام جدید', `شما ${newUnread} پیام خوانده نشده دارید`);
                }

                if (this.currentConversation) {
                    this.loadMessages(this.currentConversation.id);
                }
                this.checkIncomingCalls();
            }, 2000);

            setInterval(() => {
                this.updatePresence('online');
            }, 30000);
        },

        getTotalUnread() {
            return this.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
        },

        async loadConversations() {
            try {
                const response = await fetch('/admin/chat/conversations');
                const data = await response.json();
                this.conversations = data.conversations || [];
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
            await this.loadMessages(conv.id);
            this.$nextTick(() => this.scrollToBottom());
        },

        async loadMessages(conversationId) {
            try {
                const response = await fetch(`/admin/chat/conversations/${conversationId}/messages`);
                const data = await response.json();
                this.messages = data.messages || [];
                this.$nextTick(() => this.scrollToBottom());
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
                    this.showUsers = false;
                    await this.loadMessages(data.conversation.id);
                    await this.loadConversations();
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
                    this.showNewGroup = false;
                    this.newGroupName = '';
                    this.selectedGroupMembers = [];
                    this.currentConversation = data.conversation;
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
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {
                console.error('Error sending message:', e);
            }
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) container.scrollTop = container.scrollHeight;
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
            } catch (e) {}
        },

        async checkIncomingCalls() {
            if (this.incomingCall || this.activeCall) return; // Already in a call

            try {
                const response = await fetch('/admin/chat/calls/incoming');
                const data = await response.json();

                if (data.has_call && data.call) {
                    this.incomingCall = data.call;
                    this.$refs.ringtone.play().catch(() => {});

                    // Request notification permission and show notification
                    if (Notification.permission === 'granted') {
                        new Notification('تماس ورودی', {
                            body: `${data.call.caller_name} در حال تماس است...`,
                            icon: '/favicon.ico',
                            tag: 'incoming-call',
                            requireInteraction: true
                        });
                    }
                }
            } catch (e) {
                console.error('Error checking incoming calls:', e);
            }
        },

        async requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                await Notification.requestPermission();
            }
        },

        showNotification(title, body) {
            if (Notification.permission === 'granted') {
                new Notification(title, {
                    body: body,
                    icon: '/favicon.ico'
                });
            }
        },

        async initiateCall(userId) {
            if (!userId) return;
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
                    await this.setupWebRTC(true, userId);
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
                    const callerId = this.incomingCall.caller_id;
                    this.incomingCall = null;
                    this.$refs.ringtone.pause();
                    await this.setupWebRTC(false, callerId);
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
            } catch (e) {}
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
            } catch (e) {}
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

        async setupWebRTC(isInitiator, remoteUserId) {
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

                this.peerConnection.onicecandidate = async (event) => {
                    if (event.candidate && remoteUserId) {
                        await this.sendSignal('ice-candidate', remoteUserId, event.candidate);
                    }
                };

                if (isInitiator && remoteUserId) {
                    const offer = await this.peerConnection.createOffer();
                    await this.peerConnection.setLocalDescription(offer);
                    await this.sendSignal('offer', remoteUserId, offer);
                }
            } catch (e) {
                console.error('Error setting up WebRTC:', e);
                alert('خطا در دسترسی به میکروفون');
                this.cleanupCall();
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
            } catch (e) {}
        }
    }
}
</script>
@endsection
