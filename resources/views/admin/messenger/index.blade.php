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
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white font-bold text-lg overflow-hidden">
                            <template x-if="user.avatar">
                                <img :src="user.avatar" class="w-full h-full object-cover" :alt="user.name">
                            </template>
                            <template x-if="!user.avatar">
                                <span x-text="user.initials || user.name?.charAt(0)"></span>
                            </template>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 border-2 border-white dark:border-gray-800 rounded-full" :class="`bg-${user.status_color}-500`" :title="user.status_label"></span>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 dark:text-white" x-text="user.name"></h4>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="`bg-${user.status_color}-100 text-${user.status_color}-700 dark:bg-${user.status_color}-900/30 dark:text-${user.status_color}-300`" x-text="user.status_label"></span>
                        </div>
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
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center text-white font-bold text-lg overflow-hidden">
                            <template x-if="conv.avatar">
                                <img :src="conv.avatar" class="w-full h-full object-cover" :alt="conv.display_name">
                            </template>
                            <template x-if="!conv.avatar">
                                <span x-text="conv.initials || conv.display_name?.charAt(0)"></span>
                            </template>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 border-2 border-white dark:border-gray-800 rounded-full" :class="`bg-${conv.status_color || 'gray'}-500`" :title="conv.status_label"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-gray-900 dark:text-white truncate" x-text="conv.display_name"></h4>
                            <span class="text-xs text-gray-400" x-text="conv.last_message_time"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm text-gray-500 truncate flex-1" x-text="conv.last_message || 'شروع گفتگو...'"></p>
                        </div>
                    </div>
                    <template x-if="conv.unread_count > 0">
                        <span class="bg-brand-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[24px] text-center" x-text="conv.unread_count"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col relative">
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
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white font-bold overflow-hidden">
                            <template x-if="currentConversation?.avatar">
                                <img :src="currentConversation.avatar" class="w-full h-full object-cover" :alt="currentConversation.display_name">
                            </template>
                            <template x-if="!currentConversation?.avatar">
                                <span x-text="currentConversation?.initials || currentConversation?.display_name?.charAt(0)"></span>
                            </template>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 border-2 border-white dark:border-gray-800 rounded-full" :class="`bg-${currentConversation?.status_color || 'gray'}-500`"></span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white" x-text="currentConversation?.display_name"></h3>
                        <span class="text-xs px-2 py-0.5 rounded-full inline-flex items-center gap-1" :class="`bg-${currentConversation?.status_color || 'gray'}-100 text-${currentConversation?.status_color || 'gray'}-700 dark:bg-${currentConversation?.status_color || 'gray'}-900/30 dark:text-${currentConversation?.status_color || 'gray'}-300`">
                            <span class="w-1.5 h-1.5 rounded-full" :class="`bg-${currentConversation?.status_color || 'gray'}-500`"></span>
                            <span x-text="currentConversation?.status_label || 'آفلاین'"></span>
                        </span>
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
            <div x-ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50 dark:bg-gray-900" @click="showEmojiPicker = null">
                <template x-for="msg in messages" :key="msg.id">
                    <div :class="msg.is_mine ? 'flex justify-start' : 'flex justify-end'" class="group" :data-message-id="msg.id">
                        <div class="relative max-w-md">
                            <div :class="msg.is_mine ? 'bg-brand-500 text-white rounded-tl-none' : 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-tr-none shadow'" class="rounded-2xl px-4 py-3">
                                <!-- Reply Preview -->
                                <template x-if="msg.reply_to">
                                    <div @click.stop="scrollToMessage(msg.reply_to.id)" :class="msg.is_mine ? 'bg-brand-600/50 border-brand-300' : 'bg-gray-100 dark:bg-gray-600 border-gray-300 dark:border-gray-500'" class="mb-2 p-2 rounded-lg border-r-2 cursor-pointer text-xs">
                                        <p class="font-medium opacity-80" x-text="msg.reply_to.sender_name"></p>
                                        <p class="opacity-70 truncate" x-text="msg.reply_to.content"></p>
                                    </div>
                                </template>
                                <template x-if="currentConversation?.type === 'group' && !msg.is_mine">
                                    <p class="text-xs font-medium mb-1 opacity-75" x-text="msg.sender_name"></p>
                                </template>
                                <!-- File attachment -->
                                <template x-if="msg.type === 'file' || msg.type === 'image'">
                                    <div class="mb-2">
                                        <template x-if="msg.type === 'image'">
                                            <img :src="'/storage/' + msg.file_path" class="max-w-full rounded-lg cursor-pointer" @click="window.open('/storage/' + msg.file_path, '_blank')">
                                        </template>
                                        <template x-if="msg.type === 'file'">
                                            <a :href="'/storage/' + msg.file_path" target="_blank" class="flex items-center gap-2 p-2 bg-white/10 dark:bg-gray-600 rounded-lg">
                                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span class="text-sm" x-text="msg.file_name"></span>
                                            </a>
                                        </template>
                                    </div>
                                </template>
                                <p class="text-sm leading-relaxed" x-text="msg.content" x-show="msg.content"></p>
                                <div class="flex items-center justify-between gap-2 mt-1">
                                    <span class="text-xs opacity-60" x-text="msg.time"></span>
                                    <div class="flex items-center gap-1">
                                        <!-- Inline Actions -->
                                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click.stop="setReplyTo(msg)" class="p-1 rounded hover:bg-white/20 dark:hover:bg-black/20" :class="msg.is_mine ? 'text-white/70 hover:text-white' : 'text-gray-400 hover:text-gray-600'" title="پاسخ">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            </button>
                                            <button @click.stop="showEmojiPicker = showEmojiPicker === msg.id ? null : msg.id" class="p-1 rounded hover:bg-white/20 dark:hover:bg-black/20" :class="msg.is_mine ? 'text-white/70 hover:text-white' : 'text-gray-400 hover:text-gray-600'" title="واکنش">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </button>
                                            <button @click="copyMessage(msg.content)" class="p-1 rounded hover:bg-white/20 dark:hover:bg-black/20" :class="msg.is_mine ? 'text-white/70 hover:text-white' : 'text-gray-400 hover:text-gray-600'" title="کپی">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                        <!-- Read status ticks (only for my messages) -->
                                        <template x-if="msg.is_mine">
                                            <span class="text-xs" :class="msg.is_read ? 'text-blue-400' : 'opacity-60'">
                                                <template x-if="msg.is_read">
                                                    <span title="خوانده شده">✓✓</span>
                                                </template>
                                                <template x-if="!msg.is_read">
                                                    <span title="ارسال شده">✓</span>
                                                </template>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Reactions Display -->
                            <template x-if="msg.reactions && msg.reactions.length > 0">
                                <div class="flex flex-wrap gap-1 mt-1" :class="msg.is_mine ? 'justify-start' : 'justify-end'">
                                    <template x-for="reaction in msg.reactions" :key="reaction.emoji">
                                        <button @click.stop="toggleReaction(msg.id, reaction.emoji)" :class="reaction.has_reacted ? 'bg-brand-100 dark:bg-brand-900 border-brand-300' : 'bg-white dark:bg-gray-700'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border border-gray-200 dark:border-gray-600 hover:scale-105 transition-transform shadow-sm" :title="reaction.users.map(u => u.name).join(', ')">
                                            <span x-text="reaction.emoji"></span>
                                            <span class="text-gray-600 dark:text-gray-300" x-text="reaction.count"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Emoji Picker Bar - Shows above input when selecting reaction -->
        <template x-if="currentConversation && showEmojiPicker !== null">
            <div class="px-4 py-3 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 border-t border-gray-200 dark:border-gray-600" @click.stop>
                <div class="flex items-center gap-3">
                    <div class="flex gap-2 flex-1 justify-center">
                        <template x-for="emoji in quickEmojis" :key="emoji">
                            <button @click.stop="toggleReaction(showEmojiPicker, emoji); showEmojiPicker = null" class="w-11 h-11 flex items-center justify-center bg-white dark:bg-gray-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 rounded-xl text-2xl hover:scale-110 transition-all shadow-sm border border-gray-200 dark:border-gray-500" x-text="emoji"></button>
                        </template>
                    </div>
                    <button @click="showEmojiPicker = null" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </template>

        <!-- Reply Preview Bar -->
        <template x-if="currentConversation && replyingTo">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-600 flex items-center gap-3">
                <div class="w-1 h-12 bg-brand-500 rounded-full"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-brand-600 dark:text-brand-400" x-text="'پاسخ به ' + replyingTo.sender_name"></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 truncate" x-text="replyingTo.content"></p>
                </div>
                <button @click="replyingTo = null" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>

        <!-- Message Input -->
        <template x-if="currentConversation">
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <input type="file" x-ref="fileInput" @change="sendFile($event)" class="hidden" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                    <button @click="$refs.fileInput.click()" class="p-2 text-gray-400 hover:text-brand-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" title="ارسال فایل">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    <input x-model="newMessage" @keydown.enter="sendMessage()" @keydown.escape="replyingTo = null" x-ref="messageInput" type="text" class="flex-1 px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500" placeholder="پیام خود را بنویسید...">
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
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-[450px] max-w-[95vw] shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <div class="bg-gradient-to-r from-brand-500 to-brand-600 text-white p-6 text-center shrink-0">
                <!-- Group Avatar Upload -->
                <div class="relative w-20 h-20 mx-auto mb-3">
                    <div class="w-20 h-20 rounded-full bg-white/20 flex items-center justify-center text-3xl font-bold" x-text="newGroupName?.charAt(0) || '?'">
                    </div>
                    <label class="absolute -bottom-1 -right-1 w-8 h-8 bg-white text-brand-600 rounded-full flex items-center justify-center cursor-pointer shadow-lg hover:bg-gray-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <input type="file" accept="image/*" class="hidden" @change="handleGroupAvatar($event)">
                    </label>
                </div>
                <h3 class="text-xl font-bold">گروه جدید</h3>
                <p class="text-sm opacity-80 mt-1">یک گروه برای چت با همکاران بسازید</p>
            </div>

            <!-- Group Info -->
            <div class="p-4 space-y-3 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <input x-model="newGroupName" type="text" class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500" placeholder="نام گروه *">
                <textarea x-model="newGroupDescription" rows="2" class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500 resize-none" placeholder="توضیحات گروه (اختیاری)"></textarea>
            </div>

            <!-- Group Settings -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 space-y-3 shrink-0">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    تنظیمات گروه
                </h4>
                <label class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <input type="checkbox" x-model="groupSettings.onlyAdminsCanSend" class="w-5 h-5 text-brand-500 rounded focus:ring-brand-500">
                    <div>
                        <span class="text-gray-900 dark:text-white font-medium">فقط مدیران پیام بفرستند</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">فقط مدیران گروه می‌توانند پیام ارسال کنند</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <input type="checkbox" x-model="groupSettings.membersCanAddOthers" class="w-5 h-5 text-brand-500 rounded focus:ring-brand-500">
                    <div>
                        <span class="text-gray-900 dark:text-white font-medium">اعضا می‌توانند عضو اضافه کنند</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">همه اعضا می‌توانند افراد جدید دعوت کنند</p>
                    </div>
                </label>
            </div>

            <!-- Members Selection -->
            <div class="flex-1 overflow-y-auto">
                <div class="p-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 sticky top-0">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            انتخاب اعضا
                            <span x-show="selectedGroupMembers.length > 0" class="text-brand-500">(<span x-text="selectedGroupMembers.length"></span> نفر)</span>
                        </h4>
                        <button @click="selectedGroupMembers = users.map(u => u.id)" class="text-xs text-brand-500 hover:underline">انتخاب همه</button>
                    </div>
                </div>
                <template x-for="user in users" :key="user.id">
                    <label class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700/50">
                        <input type="checkbox" :value="user.id" x-model="selectedGroupMembers" class="w-5 h-5 text-brand-500 rounded focus:ring-brand-500">
                        <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center font-bold text-gray-600 dark:text-gray-300" x-text="user.name?.charAt(0)"></div>
                        <div class="flex-1">
                            <span class="text-gray-900 dark:text-white font-medium" x-text="user.name"></span>
                            <p class="text-xs text-gray-500" x-text="user.role || ''"></p>
                        </div>
                        <button @click.prevent="toggleGroupAdmin(user.id)" :class="groupAdmins.includes(user.id) ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'" class="px-2 py-1 text-xs rounded-lg transition" x-show="selectedGroupMembers.includes(user.id)">
                            <span x-text="groupAdmins.includes(user.id) ? 'مدیر' : 'عضو'"></span>
                        </button>
                    </label>
                </template>
            </div>

            <!-- Actions -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex gap-3 shrink-0 bg-gray-50 dark:bg-gray-900">
                <button @click="showNewGroup = false; resetGroupForm()" class="flex-1 py-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">انصراف</button>
                <button @click="createGroup()" :disabled="!newGroupName || selectedGroupMembers.length === 0" class="flex-1 py-3 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg font-medium transition">ایجاد گروه</button>
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
        newGroupDescription: '',
        selectedGroupMembers: [],
        groupAdmins: [],
        groupAvatar: null,
        groupSettings: {
            onlyAdminsCanSend: false,
            membersCanAddOthers: true
        },

        // Reply & Reaction state
        replyingTo: null,
        showEmojiPicker: null,
        quickEmojis: ['👍', '❤️', '😂', '😮', '😢', '😡'],

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
                    this.playNotificationSound();
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
                        description: this.newGroupDescription,
                        member_ids: this.selectedGroupMembers,
                        admin_ids: this.groupAdmins,
                        settings: this.groupSettings
                    })
                });
                const data = await response.json();
                if (data.conversation) {
                    this.resetGroupForm();
                    this.currentConversation = data.conversation;
                    await this.loadConversations();
                }
            } catch (e) {
                console.error('Error creating group:', e);
            }
        },

        resetGroupForm() {
            this.showNewGroup = false;
            this.newGroupName = '';
            this.newGroupDescription = '';
            this.selectedGroupMembers = [];
            this.groupAdmins = [];
            this.groupAvatar = null;
            this.groupSettings = {
                onlyAdminsCanSend: false,
                membersCanAddOthers: true
            };
        },

        toggleGroupAdmin(userId) {
            if (this.groupAdmins.includes(userId)) {
                this.groupAdmins = this.groupAdmins.filter(id => id !== userId);
            } else {
                this.groupAdmins.push(userId);
            }
        },

        handleGroupAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                this.groupAvatar = file;
            }
        },

        async sendMessage() {
            if (!this.newMessage.trim() || !this.currentConversation) return;
            const message = this.newMessage;
            const replyToId = this.replyingTo?.id || null;
            this.newMessage = '';
            this.replyingTo = null;
            try {
                const response = await fetch(`/admin/chat/conversations/${this.currentConversation.id}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ content: message, type: 'text', reply_to_id: replyToId })
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

        async sendFile(event) {
            const file = event.target.files[0];
            if (!file || !this.currentConversation) return;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', file.type.startsWith('image/') ? 'image' : 'file');

            try {
                const response = await fetch(`/admin/chat/conversations/${this.currentConversation.id}/messages`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                const data = await response.json();
                if (data.message) {
                    this.messages.push(data.message);
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {
                console.error('Error sending file:', e);
                alert('خطا در ارسال فایل');
            }

            event.target.value = '';
        },

        copyMessage(content) {
            if (!content) return;
            navigator.clipboard.writeText(content).then(() => {
                // Show toast notification
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm z-[200] animate-pulse';
                toast.textContent = 'متن کپی شد';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            }).catch(err => {
                console.error('Copy failed:', err);
            });
        },

        // Reply functions
        setReplyTo(msg) {
            this.replyingTo = {
                id: msg.id,
                content: msg.content,
                sender_name: msg.sender_name
            };
            this.$nextTick(() => {
                this.$refs.messageInput?.focus();
            });
        },

        scrollToMessage(messageId) {
            const container = this.$refs.messagesContainer;
            if (!container) return;

            const el = container.querySelector(`[data-message-id="${messageId}"]`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('bg-yellow-100', 'dark:bg-yellow-900/30', 'rounded-lg');
                setTimeout(() => {
                    el.classList.remove('bg-yellow-100', 'dark:bg-yellow-900/30', 'rounded-lg');
                }, 2000);
            }
        },

        // Reaction functions
        async toggleReaction(messageId, emoji) {
            try {
                const response = await fetch(`/admin/chat/messages/${messageId}/reaction`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ emoji })
                });
                const data = await response.json();
                if (data.success) {
                    const msg = this.messages.find(m => m.id === messageId);
                    if (msg) {
                        msg.reactions = data.reactions;
                    }
                }
            } catch (e) {
                console.error('Error toggling reaction:', e);
            }
        },

        playNotificationSound() {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleAN1qeNzAACy9l0AAMz/LxMl3P8MACX8/wAA');
            audio.volume = 0.5;
            audio.play().catch(e => {});
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

        async checkMicrophonePermission() {
            try {
                // Check if mediaDevices is supported
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    return { granted: false, error: 'مرورگر شما از تماس صوتی پشتیبانی نمی‌کند' };
                }

                // Check permission status if available
                if (navigator.permissions) {
                    try {
                        const permission = await navigator.permissions.query({ name: 'microphone' });
                        if (permission.state === 'denied') {
                            return { granted: false, error: 'دسترسی به میکروفون مسدود شده است. لطفا از تنظیمات مرورگر دسترسی را فعال کنید.' };
                        }
                    } catch (e) {
                        // permissions.query not supported for microphone in some browsers
                    }
                }

                return { granted: true };
            } catch (e) {
                return { granted: false, error: 'خطا در بررسی دسترسی میکروفون' };
            }
        },

        async requestMicrophoneAccess() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                // Immediately stop the test stream
                stream.getTracks().forEach(track => track.stop());
                return { granted: true };
            } catch (e) {
                if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                    return { granted: false, error: 'دسترسی به میکروفون رد شد. برای برقراری تماس نیاز به اجازه میکروفون است.' };
                } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
                    return { granted: false, error: 'میکروفونی یافت نشد. لطفا میکروفون خود را بررسی کنید.' };
                } else if (e.name === 'NotReadableError' || e.name === 'TrackStartError') {
                    return { granted: false, error: 'میکروفون در دسترس نیست. ممکن است برنامه دیگری از آن استفاده کند.' };
                }
                return { granted: false, error: 'خطا در دسترسی به میکروفون: ' + e.message };
            }
        },

        showMicrophoneError(message) {
            // Create toast notification for microphone error
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-96 bg-red-600 text-white p-4 rounded-xl shadow-lg z-[200] animate-pulse';
            toast.innerHTML = `
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                    <div>
                        <p class="font-bold">خطای میکروفون</p>
                        <p class="text-sm opacity-90">${message}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="mr-auto text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 8000);
        },

        async setupWebRTC(isInitiator, remoteUserId) {
            try {
                // Check microphone permission first
                const permCheck = await this.checkMicrophonePermission();
                if (!permCheck.granted) {
                    this.showMicrophoneError(permCheck.error);
                    this.cleanupCall();
                    return;
                }

                // Request microphone access with error handling
                try {
                    this.localStream = await navigator.mediaDevices.getUserMedia({
                        audio: {
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true
                        },
                        video: false
                    });
                } catch (e) {
                    const accessResult = await this.requestMicrophoneAccess();
                    if (!accessResult.granted) {
                        this.showMicrophoneError(accessResult.error);
                        this.cleanupCall();
                        return;
                    }
                    // Retry after permission check
                    this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                }

                this.$refs.localAudio.srcObject = this.localStream;

                this.peerConnection = new RTCPeerConnection({
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' },
                        { urls: 'stun:stun2.l.google.com:19302' }
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

                this.peerConnection.onconnectionstatechange = () => {
                    if (this.peerConnection.connectionState === 'failed') {
                        this.showMicrophoneError('اتصال قطع شد. لطفا دوباره تلاش کنید.');
                        this.cleanupCall();
                    }
                };

                if (isInitiator && remoteUserId) {
                    const offer = await this.peerConnection.createOffer();
                    await this.peerConnection.setLocalDescription(offer);
                    await this.sendSignal('offer', remoteUserId, offer);
                }
            } catch (e) {
                console.error('Error setting up WebRTC:', e);
                this.showMicrophoneError('خطا در برقراری تماس. لطفا دوباره تلاش کنید.');
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
