@extends('layouts.admin')

@section('page-title', 'پیام‌رسان')

@php
    $notificationSound = \App\Models\Setting::get('notification_sound');
@endphp

@section('main')
<style>
    .messenger-loading { display: flex !important; }
    [x-cloak].messenger-loading { display: flex !important; }
</style>
<div x-data="messenger()" x-init="init()" class="h-[calc(100vh-140px)] flex bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden relative">

    <!-- Loading Overlay - Always visible until Alpine hides it -->
    <div x-show="isLoading" :class="{ 'messenger-loading': isLoading }" class="messenger-loading absolute inset-0 z-[300] bg-white dark:bg-gray-800 flex items-center justify-center">
        <div class="text-center">
            <div class="w-16 h-16 border-4 border-brand-200 border-t-brand-500 rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-gray-600 dark:text-gray-400">در حال بارگذاری...</p>
        </div>
    </div>

    <!-- Sidebar - Conversations List -->
    <div class="w-full md:w-72 border-l border-gray-200 dark:border-gray-700 flex-col" :class="mobileShowChat ? 'hidden md:flex' : 'flex'">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">پیام‌ها</h2>
                <div class="relative" x-data="{ showCreateMenu: false }">
                    <button @click="showCreateMenu = !showCreateMenu" class="w-8 h-8 bg-brand-500 hover:bg-brand-600 text-white rounded-full flex items-center justify-center transition" title="ایجاد">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <!-- Create Menu Dropdown -->
                    <div x-show="showCreateMenu" @click.away="showCreateMenu = false" x-transition class="absolute left-0 top-full mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 overflow-hidden">
                        <button @click="showUsers = true; showCreateMenu = false" class="w-full px-4 py-2.5 text-right text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            ارسال پیام به اشخاص
                        </button>
                        <button @click="showNewGroup = true; createType = 'group'; showCreateMenu = false" class="w-full px-4 py-2.5 text-right text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            گروه جدید
                        </button>
                        <button @click="showNewGroup = true; createType = 'channel'; showCreateMenu = false" class="w-full px-4 py-2.5 text-right text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            کانال جدید
                        </button>
                    </div>
                </div>
            </div>
            <!-- Search -->
            <div class="relative mb-3">
                <input type="text" x-model="searchQuery" placeholder="جستجو در گفتگوها..." class="w-full px-4 py-2 pr-10 border border-gray-200 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                <svg class="w-5 h-5 absolute right-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <!-- Filter Tabs -->
            <div class="flex gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                <button @click="conversationFilter = 'all'" :class="conversationFilter === 'all' ? 'bg-white dark:bg-gray-600 shadow-sm' : ''" class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-gray-700 dark:text-gray-300">
                    همه
                </button>
                <button @click="conversationFilter = 'private'" :class="conversationFilter === 'private' ? 'bg-white dark:bg-gray-600 shadow-sm' : ''" class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-gray-700 dark:text-gray-300">
                    اعضا
                </button>
                <button @click="conversationFilter = 'group'" :class="conversationFilter === 'group' ? 'bg-white dark:bg-gray-600 shadow-sm' : ''" class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-gray-700 dark:text-gray-300">
                    گروه‌ها
                </button>
                <button @click="conversationFilter = 'channel'" :class="conversationFilter === 'channel' ? 'bg-white dark:bg-gray-600 shadow-sm' : ''" class="flex-1 py-1.5 text-xs font-medium rounded-md transition text-gray-700 dark:text-gray-300">
                    کانال‌ها
                </button>
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
            <template x-if="conversations.length === 0">
                <div class="p-8 text-center text-gray-400">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <p>هنوز گفتگویی ندارید</p>
                </div>
            </template>

            <template x-for="conv in filteredConversations" :key="conv.id">
                <div @click="openConversation(conv)" @contextmenu.prevent="openConvContextMenu($event, conv)" :class="[currentConversation?.id === conv.id ? 'bg-brand-50 dark:bg-brand-900/20 border-r-4 border-brand-500' : 'hover:bg-gray-50 dark:hover:bg-gray-700']" class="flex items-center gap-3 p-3 cursor-pointer border-b border-gray-100 dark:border-gray-700 relative">
                    <!-- Pin icon -->
                    <template x-if="conv.is_pinned_global || conv.is_pinned_personal">
                        <div class="absolute top-1 left-1">
                            <svg :class="conv.is_pinned_global ? 'text-red-400' : 'text-yellow-400'" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                        </div>
                    </template>
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <div :class="conv.type === 'channel' ? 'bg-gradient-to-br from-purple-400 to-purple-600' : (conv.type === 'group' ? 'bg-gradient-to-br from-brand-400 to-brand-600' : 'bg-gradient-to-br from-gray-400 to-gray-600')" class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold overflow-hidden">
                            <template x-if="conv.avatar">
                                <img :src="conv.avatar" class="w-full h-full object-cover" :alt="conv.display_name">
                            </template>
                            <template x-if="!conv.avatar">
                                <span x-text="conv.initials || conv.display_name?.charAt(0)"></span>
                            </template>
                        </div>
                        <!-- Online status for private chats only -->
                        <template x-if="conv.type === 'private'">
                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 border-2 border-white dark:border-gray-800 rounded-full" :class="conv.status === 'online' ? 'bg-green-500' : 'bg-gray-400'"></span>
                        </template>
                    </div>
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="font-medium text-gray-900 dark:text-white truncate text-sm" x-text="conv.display_name"></h4>
                            <span class="text-xs text-gray-400 shrink-0" x-text="conv.last_message_time"></span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5" x-text="conv.last_message || 'شروع گفتگو...'"></p>
                    </div>
                    <!-- Unread badge -->
                    <template x-if="conv.unread_count > 0">
                        <span class="bg-brand-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center shrink-0" x-text="conv.unread_count > 9 ? '9+' : conv.unread_count"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex-col relative" :class="mobileShowChat ? 'flex' : 'hidden md:flex'">
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
            <div class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <div class="p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Mobile Back Button -->
                        <button @click="closeMobileChat()" class="md:hidden p-2 -mr-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
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
                        <button @click="showMessageSearch = !showMessageSearch; if(!showMessageSearch) { messageSearchQuery = ''; clearMessageSearch(); }" :class="showMessageSearch ? 'bg-brand-100 dark:bg-brand-900 text-brand-600' : 'text-gray-500'" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" title="جستجو در پیام‌ها">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                        <button @click="alert('این قابلیت به زودی فعال خواهد شد')" x-show="currentConversation?.type === 'private'" class="p-2 text-gray-300 dark:text-gray-600 cursor-not-allowed rounded-lg opacity-50" title="تماس صوتی (به زودی)">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </button>
                    </div>
                </div>
                <!-- Message Search Bar -->
                <div x-show="showMessageSearch" x-transition class="px-4 pb-3">
                    <div class="relative">
                        <input type="text" x-model="messageSearchQuery" @input.debounce.300ms="searchMessages()" placeholder="جستجو در پیام‌های این گفتگو..." class="w-full px-4 py-2 pr-10 border border-gray-200 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                        <svg class="w-5 h-5 absolute right-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <template x-if="messageSearchResults.length > 0">
                            <div class="absolute left-3 top-2.5 flex items-center gap-2 text-xs text-gray-500">
                                <span x-text="currentSearchIndex + 1 + '/' + messageSearchResults.length"></span>
                                <button @click="navigateSearch(-1)" class="p-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <button @click="navigateSearch(1)" class="p-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
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
                                <template x-if="msg.type === 'file' || msg.type === 'image' || msg.type === 'video' || msg.type === 'audio'">
                                    <div class="mb-2">
                                        <template x-if="msg.type === 'image'">
                                            <div class="relative group/img">
                                                <img :src="'/storage/' + msg.file_path" class="max-w-full max-h-64 rounded-lg cursor-pointer hover:opacity-90 transition" @click.stop="openLightbox(msg)">
                                                <!-- Overlay with actions on hover -->
                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/img:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-3">
                                                    <button @click.stop="openLightbox(msg)" class="p-2 bg-white/20 hover:bg-white/30 rounded-full text-white transition" title="مشاهده">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    </button>
                                                    <button @click.stop="setReplyTo(msg)" class="p-2 bg-white/20 hover:bg-white/30 rounded-full text-white transition" title="پاسخ">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                                    </button>
                                                    <button @click.stop="openForwardModal(msg)" class="p-2 bg-white/20 hover:bg-white/30 rounded-full text-white transition" title="ارسال به دیگران">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="msg.type === 'video'">
                                            <div class="relative group/vid">
                                                <video :src="'/storage/' + msg.file_path" class="max-w-full max-h-64 rounded-lg" controls></video>
                                            </div>
                                        </template>
                                        <template x-if="msg.type === 'audio'">
                                            <div class="flex items-center gap-3 p-3 bg-white/10 dark:bg-gray-600 rounded-lg">
                                                <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                                </div>
                                                <audio :src="'/storage/' + msg.file_path" controls class="flex-1 h-8"></audio>
                                            </div>
                                        </template>
                                        <template x-if="msg.type === 'file'">
                                            <div @click.stop="openLightbox(msg)" class="flex items-center gap-2 p-3 bg-white/10 dark:bg-gray-600 rounded-lg cursor-pointer hover:bg-white/20 dark:hover:bg-gray-500 transition">
                                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <div class="flex-1 min-w-0">
                                                    <span class="text-sm block truncate" x-text="msg.file_name"></span>
                                                    <span class="text-xs opacity-60">کلیک برای مشاهده</span>
                                                </div>
                                            </div>
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
                                            <button @click.stop="openForwardModal(msg)" class="p-1 rounded hover:bg-white/20 dark:hover:bg-black/20" :class="msg.is_mine ? 'text-white/70 hover:text-white' : 'text-gray-400 hover:text-gray-600'" title="فوروارد">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
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
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="handleDrop($event)"
                 :class="isDragging ? 'bg-brand-50 dark:bg-brand-900/20 border-2 border-dashed border-brand-500' : ''">

                <!-- Drag overlay -->
                <div x-show="isDragging" class="absolute inset-0 flex items-center justify-center bg-brand-500/10 z-10 pointer-events-none">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto text-brand-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-brand-600 font-medium">فایل را رها کنید</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" class="hidden" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" multiple>
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

    <!-- Media Preview Modal -->
    <div x-cloak x-show="showMediaPreview" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70" @click.self="closeMediaPreview()" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col shadow-2xl">
            <!-- Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-bold text-gray-900 dark:text-white">ارسال فایل</h3>
                <button @click="closeMediaPreview()" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Preview Area -->
            <div class="flex-1 overflow-y-auto p-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <template x-for="(file, index) in selectedFiles" :key="index">
                        <div class="relative group">
                            <!-- Image Preview -->
                            <template x-if="file.type.startsWith('image/')">
                                <img :src="file.preview" class="w-full h-32 object-cover rounded-lg">
                            </template>
                            <!-- Video Preview -->
                            <template x-if="file.type.startsWith('video/')">
                                <div class="w-full h-32 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                    <video :src="file.preview" class="w-full h-full object-cover rounded-lg"></video>
                                </div>
                            </template>
                            <!-- Audio Preview -->
                            <template x-if="file.type.startsWith('audio/')">
                                <div class="w-full h-32 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex flex-col items-center justify-center p-2">
                                    <svg class="w-10 h-10 text-purple-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                    <span class="text-xs text-purple-600 dark:text-purple-300 truncate w-full text-center" x-text="file.name"></span>
                                </div>
                            </template>
                            <!-- Other Files -->
                            <template x-if="!file.type.startsWith('image/') && !file.type.startsWith('video/') && !file.type.startsWith('audio/')">
                                <div class="w-full h-32 bg-gray-100 dark:bg-gray-700 rounded-lg flex flex-col items-center justify-center p-2">
                                    <svg class="w-10 h-10 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span class="text-xs text-gray-500 truncate w-full text-center" x-text="file.name"></span>
                                </div>
                            </template>
                            <!-- Remove button -->
                            <button @click="removeFile(index)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            <!-- File size -->
                            <span class="absolute bottom-1 left-1 text-xs bg-black/50 text-white px-1.5 py-0.5 rounded" x-text="formatFileSize(file.size)"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Caption Input -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <input x-model="mediaCaption" @keydown.enter="sendMediaFiles()" type="text" class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500" placeholder="کپشن (اختیاری)...">
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <span class="text-sm text-gray-500" x-text="selectedFiles.length + ' فایل انتخاب شده'"></span>
                <div class="flex gap-2">
                    <button @click="closeMediaPreview()" class="px-4 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-400">انصراف</button>
                    <button @click="sendMediaFiles()" :disabled="isSendingMedia" class="px-6 py-2 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition flex items-center gap-2">
                        <template x-if="isSendingMedia">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </template>
                        <span x-text="isSendingMedia ? 'در حال ارسال...' : 'ارسال'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Phone Modal -->
    <div x-cloak x-show="showPhone" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPhone = false" style="display: none;">
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

    <!-- New Group/Channel Modal -->
    <div x-cloak x-show="showNewGroup" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showNewGroup = false" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-[400px] max-w-[95vw] shadow-2xl overflow-hidden">
            <!-- Header -->
            <div :class="createType === 'channel' ? 'from-purple-500 to-purple-600' : 'from-brand-500 to-brand-600'" class="bg-gradient-to-r text-white p-5 text-center">
                <h3 class="text-xl font-bold" x-text="createType === 'channel' ? 'کانال جدید' : 'گروه جدید'"></h3>
            </div>

            <!-- Content -->
            <div class="p-5 space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" x-text="createType === 'channel' ? 'نام کانال *' : 'نام گروه *'"></label>
                    <input x-model="newGroupName" type="text" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500" :placeholder="createType === 'channel' ? 'نام کانال را وارد کنید' : 'نام گروه را وارد کنید'">
                </div>

                <!-- Avatar -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" x-text="createType === 'channel' ? 'تصویر کانال' : 'تصویر گروه'"></label>
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-xl font-bold text-gray-500 dark:text-gray-300 overflow-hidden border-2 border-gray-300 dark:border-gray-500">
                            <template x-if="groupAvatarPreview">
                                <img :src="groupAvatarPreview" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!groupAvatarPreview">
                                <span x-text="newGroupName?.charAt(0) || '؟'"></span>
                            </template>
                        </div>
                        <label class="flex-1 px-4 py-2.5 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <span class="text-sm text-gray-500 dark:text-gray-400">انتخاب تصویر</span>
                            <input type="file" accept="image/*" class="hidden" @change="handleGroupAvatar($event)">
                        </label>
                    </div>
                </div>

                <!-- Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" x-text="createType === 'channel' ? 'نوع کانال' : 'نوع گروه'"></label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition" :class="groupSettings.isPublic ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
                            <input type="radio" name="groupType" :checked="groupSettings.isPublic" @click="groupSettings.isPublic = true" class="w-4 h-4 text-brand-500">
                            <div>
                                <span class="text-gray-900 dark:text-white font-medium" x-text="createType === 'channel' ? 'کانال عمومی' : 'گروه عمومی'"></span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">همه می‌توانند ببینند و عضو شوند</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition" :class="!groupSettings.isPublic ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
                            <input type="radio" name="groupType" :checked="!groupSettings.isPublic" @click="groupSettings.isPublic = false" class="w-4 h-4 text-brand-500">
                            <div>
                                <span class="text-gray-900 dark:text-white font-medium">انتخاب اعضا</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">فقط اعضای انتخاب شده دسترسی دارند</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Pin for all -->
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="groupSettings.isPinned" id="pinForAll" class="w-4 h-4 text-brand-500 rounded">
                    <label for="pinForAll" class="text-sm text-gray-700 dark:text-gray-300">پین برای همه کاربران</label>
                </div>

                <!-- Members Selection (only if not public) -->
                <div x-show="!groupSettings.isPublic" x-transition>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <span x-text="createType === 'channel' ? 'اعضای کانال' : 'اعضای گروه'"></span>
                        <span x-show="selectedGroupMembers.length > 0" class="text-brand-500 text-xs">(<span x-text="selectedGroupMembers.length"></span> نفر)</span>
                    </label>
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg max-h-40 overflow-y-auto">
                        <template x-for="user in users" :key="user.id">
                            <label class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700/50 last:border-0">
                                <input type="checkbox" :value="user.id" x-model="selectedGroupMembers" class="w-4 h-4 text-brand-500 rounded">
                                <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-sm font-bold text-gray-600 dark:text-gray-300" x-text="user.name?.charAt(0)"></div>
                                <span class="text-sm text-gray-900 dark:text-white" x-text="user.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex gap-3 bg-gray-50 dark:bg-gray-900">
                <button @click="showNewGroup = false; resetGroupForm()" class="flex-1 py-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">انصراف</button>
                <button @click="createGroup()" :disabled="isCreatingGroup || !newGroupName || (!groupSettings.isPublic && selectedGroupMembers.length === 0)" :class="createType === 'channel' ? 'bg-purple-500 hover:bg-purple-600' : 'bg-brand-500 hover:bg-brand-600'" class="flex-1 py-3 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg font-medium transition">
                    <span x-show="!isCreatingGroup" x-text="createType === 'channel' ? 'ایجاد کانال' : 'ایجاد گروه'"></span>
                    <span x-show="isCreatingGroup" class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        در حال ایجاد...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Group/Channel Modal -->
    <div x-cloak x-show="showEditGroup" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showEditGroup = false" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-[400px] max-w-[95vw] shadow-2xl overflow-hidden">
            <!-- Header -->
            <div :class="editingConversation?.type === 'channel' ? 'from-purple-500 to-purple-600' : 'from-brand-500 to-brand-600'" class="bg-gradient-to-r text-white p-5 text-center">
                <h3 class="text-xl font-bold" x-text="editingConversation?.type === 'channel' ? 'ویرایش کانال' : 'ویرایش گروه'"></h3>
            </div>

            <!-- Content -->
            <div class="p-5 space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام *</label>
                    <input x-model="editGroupName" type="text" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                </div>

                <!-- Avatar -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تصویر</label>
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-xl font-bold text-gray-500 dark:text-gray-300 overflow-hidden border-2 border-gray-300 dark:border-gray-500">
                            <template x-if="editGroupAvatarPreview || editingConversation?.avatar">
                                <img :src="editGroupAvatarPreview || editingConversation?.avatar" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!editGroupAvatarPreview && !editingConversation?.avatar">
                                <span x-text="editGroupName?.charAt(0) || '؟'"></span>
                            </template>
                        </div>
                        <label class="flex-1 px-4 py-2.5 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <span class="text-sm text-gray-500 dark:text-gray-400">تغییر تصویر</span>
                            <input type="file" accept="image/*" class="hidden" @change="handleEditGroupAvatar($event)">
                        </label>
                    </div>
                </div>

                <!-- Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">دسترسی</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition" :class="editGroupSettings.isPublic ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
                            <input type="radio" name="editGroupType" :checked="editGroupSettings.isPublic" @click="editGroupSettings.isPublic = true" class="w-4 h-4 text-brand-500">
                            <div>
                                <span class="text-gray-900 dark:text-white font-medium">عمومی</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">همه می‌توانند ببینند و عضو شوند</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition" :class="!editGroupSettings.isPublic ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'">
                            <input type="radio" name="editGroupType" :checked="!editGroupSettings.isPublic" @click="editGroupSettings.isPublic = false" class="w-4 h-4 text-brand-500">
                            <div>
                                <span class="text-gray-900 dark:text-white font-medium">خصوصی</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">فقط اعضای انتخاب شده دسترسی دارند</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Pin for all -->
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="editGroupSettings.isPinned" id="editPinForAll" class="w-4 h-4 text-brand-500 rounded">
                    <label for="editPinForAll" class="text-sm text-gray-700 dark:text-gray-300">پین برای همه کاربران</label>
                </div>

                <!-- Members Management -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        مدیریت اعضا
                        <span x-show="editGroupMembers.length > 0" class="text-brand-500 text-xs">(<span x-text="editGroupMembers.length"></span> نفر)</span>
                    </label>
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg max-h-40 overflow-y-auto">
                        <template x-for="user in users" :key="user.id">
                            <label class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700/50 last:border-0">
                                <input type="checkbox" :value="user.id" x-model="editGroupMembers" class="w-4 h-4 text-brand-500 rounded">
                                <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-sm font-bold text-gray-600 dark:text-gray-300" x-text="user.name?.charAt(0)"></div>
                                <span class="text-sm text-gray-900 dark:text-white" x-text="user.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex gap-3 bg-gray-50 dark:bg-gray-900">
                <button @click="showEditGroup = false" class="flex-1 py-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">انصراف</button>
                <button @click="updateGroup()" :disabled="!editGroupName" class="flex-1 py-3 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg font-medium transition">ذخیره تغییرات</button>
            </div>
        </div>
    </div>

    <!-- Incoming Call Modal -->
    <div x-cloak x-show="incomingCall" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70" style="display: none;">
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
    <div x-cloak x-show="activeCall" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800" style="display: none;">
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

    <!-- Lightbox Modal -->
    <div x-cloak x-show="lightbox" x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90" @click="lightbox = null" @keydown.escape.window="lightbox = null" style="display: none;">
        <div class="relative max-w-4xl max-h-[90vh] w-full mx-4" @click.stop>
            <!-- Close button -->
            <button @click="lightbox = null" class="absolute -top-12 right-0 p-2 text-white/70 hover:text-white transition">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Image -->
            <template x-if="lightbox?.type === 'image'">
                <img :src="'/storage/' + lightbox.file_path" class="max-w-full max-h-[80vh] mx-auto rounded-lg shadow-2xl">
            </template>

            <!-- Video -->
            <template x-if="lightbox?.type === 'video'">
                <video :src="'/storage/' + lightbox.file_path" class="max-w-full max-h-[80vh] mx-auto rounded-lg shadow-2xl" controls autoplay></video>
            </template>

            <!-- Audio -->
            <template x-if="lightbox?.type === 'audio'">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center max-w-md mx-auto">
                    <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-purple-500 flex items-center justify-center">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4" x-text="lightbox.file_name"></h3>
                    <audio :src="'/storage/' + lightbox.file_path" controls class="w-full"></audio>
                </div>
            </template>

            <!-- File preview -->
            <template x-if="lightbox?.type === 'file'">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center max-w-md mx-auto">
                    <svg class="w-20 h-20 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2" x-text="lightbox.file_name"></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">برای دانلود روی دکمه زیر کلیک کنید</p>
                </div>
            </template>

            <!-- Actions -->
            <div class="flex justify-center gap-4 mt-4">
                <a :href="'/storage/' + lightbox?.file_path" download class="inline-flex items-center gap-2 px-6 py-3 bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    دانلود
                </a>
                <button @click="openForwardModal(lightbox); lightbox = null" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-xl transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    فوروارد
                </button>
            </div>
        </div>
    </div>

    <!-- Conversation Context Menu -->
    <div x-cloak x-show="convContextMenu.show" x-transition @click.away="convContextMenu.show = false" :style="`top: ${convContextMenu.y}px; left: ${convContextMenu.x}px;`" class="fixed z-[200] bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 py-1 w-48" style="display: none;">
        <!-- Pin Personal -->
        <button @click="togglePersonalPin(convContextMenu.conv); convContextMenu.show = false" class="w-full px-4 py-2 text-right text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            <span x-text="convContextMenu.conv?.is_pinned_personal ? 'برداشتن پین شخصی' : 'پین شخصی'"></span>
        </button>
        <!-- Pin Global (Admin only) -->
        <template x-if="convContextMenu.conv?.type === 'group' || convContextMenu.conv?.type === 'channel'">
            <button @click="toggleGlobalPin(convContextMenu.conv); convContextMenu.show = false" class="w-full px-4 py-2 text-right text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4" :class="convContextMenu.conv?.is_pinned_global ? 'text-red-500' : ''" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                <span x-text="convContextMenu.conv?.is_pinned_global ? 'برداشتن پین همگانی' : 'پین برای همه'"></span>
            </button>
        </template>
        <!-- Edit Group/Channel -->
        <template x-if="convContextMenu.conv?.type === 'group' || convContextMenu.conv?.type === 'channel'">
            <button @click="openEditGroup(convContextMenu.conv); convContextMenu.show = false" class="w-full px-4 py-2 text-right text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span x-text="convContextMenu.conv?.type === 'channel' ? 'ویرایش کانال' : 'ویرایش گروه'"></span>
            </button>
        </template>
        <!-- Delete Group/Channel (Creator only) -->
        <template x-if="convContextMenu.conv?.type === 'group' || convContextMenu.conv?.type === 'channel'">
            <button @click="openDeleteModal(convContextMenu.conv); convContextMenu.show = false" class="w-full px-4 py-2 text-right text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span x-text="convContextMenu.conv?.type === 'channel' ? 'حذف کانال' : 'حذف گروه'"></span>
            </button>
        </template>
    </div>

    <!-- Forward Modal -->
    <div x-cloak x-show="showForwardModal" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50" @click.self="showForwardModal = false; forwardingMessage = null" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-96 max-h-[80vh] shadow-2xl overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">فوروارد پیام</h3>
                    <button @click="showForwardModal = false; forwardingMessage = null" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Preview of forwarding message -->
                <div class="mt-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-gray-600 dark:text-gray-300 truncate" x-show="forwardingMessage">
                    <template x-if="forwardingMessage?.type === 'image'">
                        <span class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> تصویر</span>
                    </template>
                    <template x-if="forwardingMessage?.type === 'file'">
                        <span class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> <span x-text="forwardingMessage.file_name"></span></span>
                    </template>
                    <template x-if="forwardingMessage?.type === 'text'">
                        <span x-text="forwardingMessage.content"></span>
                    </template>
                </div>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <p class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">انتخاب گفتگو:</p>
                <template x-for="conv in conversations" :key="conv.id">
                    <div @click="forwardMessage(conv.id)" class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-bold overflow-hidden">
                                <template x-if="conv.avatar">
                                    <img :src="conv.avatar" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!conv.avatar">
                                    <span x-text="conv.display_name?.charAt(0)"></span>
                                </template>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white" x-text="conv.display_name"></h4>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-cloak x-show="showDeleteModal" x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50" @click.self="showDeleteModal = false" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-96 shadow-2xl overflow-hidden mx-4">
            <div class="bg-red-500 text-white p-5 text-center">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <h3 class="text-xl font-bold" x-text="deleteConversation?.type === 'channel' ? 'حذف کانال' : 'حذف گروه'"></h3>
            </div>
            <div class="p-5 space-y-4">
                <div class="text-center">
                    <p class="text-gray-700 dark:text-gray-300 mb-2">آیا از حذف <span class="font-bold" x-text="deleteConversation?.display_name"></span> مطمئن هستید؟</p>
                    <p class="text-sm text-red-500">این عمل قابل بازگشت نیست و تمام پیام‌ها حذف خواهند شد.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">برای تایید، کد زیر را وارد کنید:</label>
                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 text-center mb-3">
                        <span class="text-2xl font-mono font-bold tracking-widest text-red-600" x-text="expectedDeleteCode"></span>
                    </div>
                    <input type="text" x-model="deleteConfirmCode" placeholder="کد تایید" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-500 text-center font-mono text-lg tracking-widest" maxlength="4">
                </div>
                <div class="flex gap-3 pt-2">
                    <button @click="showDeleteModal = false; deleteConfirmCode = ''" class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition">انصراف</button>
                    <button @click="confirmDelete()" :disabled="isDeleting || deleteConfirmCode !== expectedDeleteCode" class="flex-1 py-3 bg-red-500 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg font-medium hover:bg-red-600 transition">
                        <span x-show="!isDeleting">حذف</span>
                        <span x-show="isDeleting" class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            در حال حذف...
                        </span>
                    </button>
                </div>
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
        isLoading: true,
        conversations: [],
        users: [],
        messages: [],
        currentConversation: null,
        newMessage: '',
        searchQuery: '',
        conversationFilter: 'all',
        mobileShowChat: false,
        showUsers: false,
        showPhone: false,
        showNewGroup: false,
        createType: 'group', // 'group' or 'channel'
        newGroupName: '',
        newGroupDescription: '',
        selectedGroupMembers: [],
        groupAdmins: [],
        groupAvatar: null,
        groupSettings: {
            isPublic: false,
            onlyAdminsCanSend: false,
            membersCanAddOthers: true,
            isPinned: false
        },
        groupAvatarPreview: null,
        isCreatingGroup: false,

        // Edit group state
        showEditGroup: false,
        editingConversation: null,
        editGroupName: '',
        editGroupAvatar: null,
        editGroupAvatarPreview: null,
        editGroupMembers: [],
        editGroupSettings: {
            isPublic: false,
            isPinned: false
        },

        // Context menu state
        convContextMenu: {
            show: false,
            x: 0,
            y: 0,
            conv: null
        },

        // Delete confirmation state
        showDeleteModal: false,
        deleteConversation: null,
        deleteConfirmCode: '',
        expectedDeleteCode: '',
        isDeleting: false,

        // Reply & Reaction state
        replyingTo: null,
        showEmojiPicker: null,
        quickEmojis: ['👍', '❤️', '😂', '😮', '😢', '😡'],

        // Lightbox state
        lightbox: null,

        // Forward state
        forwardingMessage: null,
        showForwardModal: false,

        // Message search state
        showMessageSearch: false,
        messageSearchQuery: '',
        messageSearchResults: [],
        currentSearchIndex: 0,

        // Call state
        incomingCall: null,
        activeCall: null,
        callDuration: '00:00',
        callTimer: null,
        isMuted: false,
        peerConnection: null,
        localStream: null,

        // Media upload state
        showMediaPreview: false,
        selectedFiles: [],
        mediaCaption: '',
        isDragging: false,
        isSendingMedia: false,

        get filteredConversations() {
            let filtered = this.conversations;

            // Sort by pinned first
            filtered = [...filtered].sort((a, b) => {
                if (a.is_pinned_global && !b.is_pinned_global) return -1;
                if (!a.is_pinned_global && b.is_pinned_global) return 1;
                if (a.is_pinned_personal && !b.is_pinned_personal) return -1;
                if (!a.is_pinned_personal && b.is_pinned_personal) return 1;
                return 0;
            });

            // Apply type filter
            if (this.conversationFilter === 'private') {
                filtered = filtered.filter(c => c.type === 'private');
            } else if (this.conversationFilter === 'group') {
                filtered = filtered.filter(c => c.type === 'group');
            } else if (this.conversationFilter === 'channel') {
                filtered = filtered.filter(c => c.type === 'channel');
            }

            // Apply search filter
            if (this.searchQuery) {
                filtered = filtered.filter(c =>
                    c.display_name?.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }

            return filtered;
        },

        get filteredUsers() {
            if (!this.searchQuery) return this.users;
            return this.users.filter(u =>
                u.name?.toLowerCase().includes(this.searchQuery.toLowerCase())
            );
        },

        async init() {
            try {
                await this.loadConversations();
                await this.loadUsers();
                this.heartbeat();
            } finally {
                this.isLoading = false;
            }

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
                this.heartbeat();
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
            this.mobileShowChat = true;
            await this.loadMessages(conv.id);
            this.$nextTick(() => this.scrollToBottom());
        },

        closeMobileChat() {
            this.mobileShowChat = false;
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
                    this.mobileShowChat = true;
                    await this.loadMessages(data.conversation.id);
                    await this.loadConversations();
                }
            } catch (e) {
                console.error('Error starting conversation:', e);
            }
        },

        async createGroup() {
            if (!this.newGroupName) return;
            // If not public group, need members
            if (!this.groupSettings.isPublic && this.selectedGroupMembers.length === 0) {
                alert('لطفا حداقل یک عضو انتخاب کنید یا گروه را عمومی کنید');
                return;
            }

            const typeLabel = this.createType === 'channel' ? 'کانال' : 'گروه';

            try {
                const formData = new FormData();
                formData.append('name', this.newGroupName);
                formData.append('description', this.newGroupDescription || '');
                formData.append('type', this.createType); // 'group' or 'channel'
                formData.append('member_ids', JSON.stringify(this.selectedGroupMembers));
                formData.append('admin_ids', JSON.stringify(this.groupAdmins));
                formData.append('settings', JSON.stringify(this.groupSettings));

                if (this.groupAvatar) {
                    formData.append('avatar', this.groupAvatar);
                }

                this.isCreatingGroup = true;
                const response = await fetch('/admin/chat/conversations/group', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                let data;
                try {
                    data = await response.json();
                } catch (jsonErr) {
                    this.isCreatingGroup = false;
                    alert('خطا در پردازش پاسخ سرور');
                    return;
                }

                this.isCreatingGroup = false;

                // Handle errors
                if (!response.ok) {
                    const errorMsg = data.error || data.message || 'خطا در ایجاد ' + typeLabel;
                    alert(errorMsg);
                    return;
                }

                // Success - close modal and show message
                this.showNewGroup = false;

                // Show success toast
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-green-600 text-white px-6 py-3 rounded-lg text-sm z-[200] shadow-lg';
                toast.textContent = typeLabel + ' با موفقیت ایجاد شد';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);

                // Reset form data
                this.createType = 'group';
                this.newGroupName = '';
                this.newGroupDescription = '';
                this.selectedGroupMembers = [];
                this.groupAdmins = [];
                this.groupAvatar = null;
                this.groupAvatarPreview = null;
                this.groupSettings = { isPublic: false, onlyAdminsCanSend: false, membersCanAddOthers: true, isPinned: false };

                // Load conversations and open the new one
                if (data.conversation && data.conversation.id) {
                    const conversationId = data.conversation.id;
                    await this.loadConversations();

                    // Find and open the created conversation
                    const newConv = this.conversations.find(c => c.id === conversationId);
                    if (newConv) {
                        this.currentConversation = newConv;
                        this.mobileShowChat = true;
                        await this.loadMessages(conversationId);
                    }
                } else {
                    await this.loadConversations();
                }
            } catch (e) {
                this.isCreatingGroup = false;
                console.error('Error creating group:', e);
                alert('خطا در ایجاد ' + typeLabel + ': ' + e.message);
            }
        },

        resetGroupForm() {
            this.showNewGroup = false;
            this.createType = 'group';
            this.newGroupName = '';
            this.newGroupDescription = '';
            this.selectedGroupMembers = [];
            this.groupAdmins = [];
            this.groupAvatar = null;
            this.groupAvatarPreview = null;
            this.groupSettings = {
                isPublic: false,
                onlyAdminsCanSend: false,
                membersCanAddOthers: true,
                isPinned: false
            };
        },

        // Edit group functions
        openEditGroup(conv) {
            this.editingConversation = conv;
            this.editGroupName = conv.display_name;
            this.editGroupAvatarPreview = null;
            this.editGroupAvatar = null;
            this.editGroupSettings = {
                isPublic: conv.is_public || false,
                isPinned: conv.is_pinned_global || false
            };
            this.editGroupMembers = conv.member_ids || [];
            this.showEditGroup = true;
        },

        handleEditGroupAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                this.editGroupAvatar = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.editGroupAvatarPreview = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },

        async updateGroup() {
            if (!this.editGroupName || !this.editingConversation) return;
            try {
                const formData = new FormData();
                formData.append('name', this.editGroupName);
                formData.append('settings', JSON.stringify(this.editGroupSettings));
                formData.append('member_ids', JSON.stringify(this.editGroupMembers));

                if (this.editGroupAvatar) {
                    formData.append('avatar', this.editGroupAvatar);
                }

                const response = await fetch(`/admin/chat/conversations/${this.editingConversation.id}/update`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    this.showEditGroup = false;
                    await this.loadConversations();
                    // Update current conversation if it's the one being edited
                    if (this.currentConversation?.id === this.editingConversation.id) {
                        const updated = this.conversations.find(c => c.id === this.editingConversation.id);
                        if (updated) this.currentConversation = updated;
                    }
                }
            } catch (e) {
                console.error('Error updating group:', e);
            }
        },

        // Delete group/channel functions
        openDeleteModal(conv) {
            this.deleteConversation = conv;
            this.deleteConfirmCode = '';
            // Generate a random 4-digit code
            this.expectedDeleteCode = Math.floor(1000 + Math.random() * 9000).toString();
            this.showDeleteModal = true;
        },

        async confirmDelete() {
            if (this.deleteConfirmCode !== this.expectedDeleteCode || !this.deleteConversation) return;

            this.isDeleting = true;
            try {
                const response = await fetch(`/admin/chat/conversations/${this.deleteConversation.id}/delete`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ confirmation_code: this.deleteConfirmCode })
                });
                const data = await response.json();
                this.isDeleting = false;

                if (data.error) {
                    alert(data.error);
                    return;
                }

                if (data.success) {
                    const typeLabel = this.deleteConversation.type === 'channel' ? 'کانال' : 'گروه';
                    // Close modal and clear current conversation if it was the deleted one
                    if (this.currentConversation?.id === this.deleteConversation.id) {
                        this.currentConversation = null;
                        this.messages = [];
                        this.mobileShowChat = false;
                    }
                    this.showDeleteModal = false;
                    this.deleteConversation = null;
                    this.deleteConfirmCode = '';
                    await this.loadConversations();

                    // Show success toast
                    const toast = document.createElement('div');
                    toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm z-[200]';
                    toast.textContent = typeLabel + ' با موفقیت حذف شد';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 2000);
                }
            } catch (e) {
                this.isDeleting = false;
                console.error('Error deleting group:', e);
                alert('خطا در حذف');
            }
        },

        // Context menu functions
        openConvContextMenu(event, conv) {
            this.convContextMenu = {
                show: true,
                x: event.clientX,
                y: event.clientY,
                conv: conv
            };
        },

        // Pin functions
        async togglePersonalPin(conv) {
            if (!conv) return;
            try {
                const response = await fetch(`/admin/chat/conversations/${conv.id}/pin/personal`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (data.success) {
                    await this.loadConversations();
                } else if (data.error) {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Error toggling personal pin:', e);
            }
        },

        async toggleGlobalPin(conv) {
            if (!conv) return;
            try {
                const response = await fetch(`/admin/chat/conversations/${conv.id}/pin/global`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (data.success) {
                    await this.loadConversations();
                }
            } catch (e) {
                console.error('Error toggling global pin:', e);
            }
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
                // Create preview URL
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.groupAvatarPreview = e.target.result;
                };
                reader.readAsDataURL(file);
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

        // Multi-media upload functions
        handleDrop(event) {
            event.preventDefault();
            this.isDragging = false;
            const files = Array.from(event.dataTransfer.files);
            this.addFilesToSelection(files);
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.addFilesToSelection(files);
            event.target.value = '';
        },

        addFilesToSelection(files) {
            files.forEach(file => {
                // Create preview URL for images and videos
                let preview = null;
                if (file.type.startsWith('image/')) {
                    preview = URL.createObjectURL(file);
                } else if (file.type.startsWith('video/')) {
                    preview = URL.createObjectURL(file);
                }

                this.selectedFiles.push({
                    file: file,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    preview: preview
                });
            });

            if (this.selectedFiles.length > 0) {
                this.showMediaPreview = true;
            }
        },

        removeFile(index) {
            // Revoke object URL to free memory
            if (this.selectedFiles[index].preview) {
                URL.revokeObjectURL(this.selectedFiles[index].preview);
            }
            this.selectedFiles.splice(index, 1);

            if (this.selectedFiles.length === 0) {
                this.closeMediaPreview();
            }
        },

        closeMediaPreview() {
            // Revoke all object URLs
            this.selectedFiles.forEach(f => {
                if (f.preview) URL.revokeObjectURL(f.preview);
            });
            this.selectedFiles = [];
            this.mediaCaption = '';
            this.showMediaPreview = false;
        },

        async sendMediaFiles() {
            if (!this.currentConversation || this.selectedFiles.length === 0) return;

            this.isSendingMedia = true;

            try {
                for (const fileData of this.selectedFiles) {
                    const formData = new FormData();
                    formData.append('file', fileData.file);

                    // Determine type based on file mime type
                    let type = 'file';
                    if (fileData.type.startsWith('image/')) type = 'image';
                    else if (fileData.type.startsWith('video/')) type = 'video';
                    else if (fileData.type.startsWith('audio/')) type = 'audio';

                    formData.append('type', type);

                    // Add caption only to the last file
                    if (this.mediaCaption && fileData === this.selectedFiles[this.selectedFiles.length - 1]) {
                        formData.append('caption', this.mediaCaption);
                    }

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
                    }
                }

                this.$nextTick(() => this.scrollToBottom());
                this.closeMediaPreview();

            } catch (e) {
                console.error('Error sending media:', e);
                alert('خطا در ارسال فایل‌ها');
            } finally {
                this.isSendingMedia = false;
            }
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        getFileIcon(type) {
            if (type.startsWith('image/')) return '🖼️';
            if (type.startsWith('video/')) return '🎬';
            if (type.startsWith('audio/')) return '🎵';
            if (type.includes('pdf')) return '📄';
            if (type.includes('word') || type.includes('document')) return '📝';
            if (type.includes('excel') || type.includes('spreadsheet')) return '📊';
            return '📎';
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
            let content = msg.content;
            // For image/file messages, show appropriate placeholder
            if (msg.type === 'image') {
                content = '📷 تصویر';
            } else if (msg.type === 'video') {
                content = '🎬 ویدیو';
            } else if (msg.type === 'audio') {
                content = '🎵 صوت';
            } else if (msg.type === 'file') {
                content = '📎 ' + (msg.file_name || 'فایل');
            }
            this.replyingTo = {
                id: msg.id,
                content: content,
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

        // Message search functions
        searchMessages() {
            if (!this.messageSearchQuery || this.messageSearchQuery.length < 2) {
                this.messageSearchResults = [];
                this.currentSearchIndex = 0;
                return;
            }

            const query = this.messageSearchQuery.toLowerCase();
            this.messageSearchResults = this.messages
                .filter(m => m.content && m.content.toLowerCase().includes(query))
                .map(m => m.id);

            this.currentSearchIndex = 0;

            if (this.messageSearchResults.length > 0) {
                this.scrollToMessage(this.messageSearchResults[0]);
            }
        },

        navigateSearch(direction) {
            if (this.messageSearchResults.length === 0) return;

            this.currentSearchIndex = (this.currentSearchIndex + direction + this.messageSearchResults.length) % this.messageSearchResults.length;
            this.scrollToMessage(this.messageSearchResults[this.currentSearchIndex]);
        },

        clearMessageSearch() {
            this.messageSearchQuery = '';
            this.messageSearchResults = [];
            this.currentSearchIndex = 0;
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
            @if($notificationSound)
            const audio = new Audio('{{ asset("storage/" . $notificationSound) }}');
            @else
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleAN1qeNzAACy9l0AAMz/LxMl3P8MACX8/wAA');
            @endif
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

        async heartbeat() {
            try {
                await fetch('/admin/chat/heartbeat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
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
        },

        // Lightbox functions
        openLightbox(msg) {
            this.lightbox = msg;
        },

        // Forward functions
        openForwardModal(msg) {
            this.forwardingMessage = msg;
            this.showForwardModal = true;
        },

        async forwardMessage(conversationId) {
            if (!this.forwardingMessage || !conversationId) return;

            try {
                const formData = new FormData();
                formData.append('type', this.forwardingMessage.type || 'text');
                formData.append('content', this.forwardingMessage.content || '');
                formData.append('forwarded_from', this.forwardingMessage.id);

                if (this.forwardingMessage.file_path) {
                    formData.append('file_path', this.forwardingMessage.file_path);
                    formData.append('file_name', this.forwardingMessage.file_name || '');
                }

                const response = await fetch(`/admin/chat/conversations/${conversationId}/messages`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                const data = await response.json();
                if (data.message) {
                    // Show success toast
                    const toast = document.createElement('div');
                    toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm z-[200]';
                    toast.textContent = 'پیام فوروارد شد';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 2000);

                    // If forwarding to current conversation, add to messages
                    if (this.currentConversation?.id === conversationId) {
                        this.messages.push(data.message);
                        this.$nextTick(() => this.scrollToBottom());
                    }
                }
            } catch (e) {
                console.error('Error forwarding message:', e);
                alert('خطا در فوروارد پیام');
            }

            this.showForwardModal = false;
            this.forwardingMessage = null;
        },

        async joinGroup(conversationId) {
            try {
                const response = await fetch(`/admin/chat/conversations/${conversationId}/join`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (data.success) {
                    // Show success toast
                    const toast = document.createElement('div');
                    toast.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm z-[200]';
                    toast.textContent = 'به گروه پیوستید';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 2000);

                    // Reload conversations
                    await this.loadConversations();

                    // Open the conversation
                    const conv = this.conversations.find(c => c.id === conversationId);
                    if (conv) {
                        this.openConversation(conv);
                    }
                } else if (data.error) {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Error joining group:', e);
                alert('خطا در پیوستن به گروه');
            }
        }
    }
}
</script>
@endsection
