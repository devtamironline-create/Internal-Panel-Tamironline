@extends('layouts.admin')

@php
    $techName = trim(($technician->firstname_tech ?: ($technician->first_name . ' ' . ($technician->last_name ?? ''))));
    $techName = $techName !== '' ? $techName : ('تکنسین #' . $technician->id);
@endphp

@section('page-title', 'گفت‌وگو با ' . $techName)

@section('main')
<div class="p-4 md:p-6 max-w-3xl mx-auto" x-data="techChat()" x-init="init()">
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('crm.tech-chats.index') }}" class="text-sm text-brand-700 hover:underline">← لیست</a>
        <div class="ms-auto text-sm text-gray-500" dir="ltr">{{ $technician->mobile }}</div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow flex flex-col" style="height: 70vh;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center text-brand-700 dark:text-brand-300 font-bold">
                {{ mb_substr($techName, 0, 1) }}
            </div>
            <div>
                <div class="font-bold text-gray-900 dark:text-gray-100">{{ $techName }}</div>
                <div class="text-[11px] text-emerald-600">آنلاین در پنل تکنسین</div>
            </div>
        </div>

        <div id="chat-stream" class="flex-1 overflow-y-auto p-4 space-y-2" style="background: #f9fafb;">
            @foreach($messages as $msg)
                @php $mine = $msg->sender_type === \Modules\CRM\Models\TechChatMessage::SENDER_OPERATOR; @endphp
                <div class="flex {{ $mine ? 'justify-start' : 'justify-end' }}" data-msg-id="{{ $msg->id }}">
                    <div class="max-w-[78%] {{ $mine ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200' }} rounded-2xl px-3 py-2 text-sm">
                        <div class="whitespace-pre-wrap break-words">{{ $msg->body }}</div>
                        <div class="text-[10px] mt-1 opacity-70 text-end">@jdatetime($msg->created_at)</div>
                    </div>
                </div>
            @endforeach
        </div>

        <form @submit.prevent="send()" class="p-3 border-t border-gray-100 dark:border-gray-700 flex items-end gap-2">
            {{-- اینتر = خطِ بعدی + کش‌آمدنِ خودکارِ باکس (تا سقف ~۶ خط، بعد اسکرول
                 داخلی). ارسال با دکمه یا Ctrl+Enter. --}}
            <textarea x-ref="input" x-model="body" rows="1" placeholder="پیام بنویسید… (Ctrl+Enter = ارسال)"
                      @input="autoGrow()"
                      @keydown.ctrl.enter.prevent="send()"
                      style="max-height: 160px; overflow-y: auto;"
                      class="flex-1 resize-none border border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 rounded-xl px-3 py-2 text-sm leading-6 focus:outline-none focus:border-brand-400"></textarea>
            <button type="submit" :disabled="!body.trim() || sending"
                    class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-bold disabled:opacity-50">
                ارسال
            </button>
        </form>
    </div>
</div>

<script>
function techChat() {
    return {
        body: '',
        sending: false,
        lastId: {{ $messages->max('id') ?? 0 }},
        pollHandle: null,

        init() {
            this.scrollBottom();
            this.pollHandle = setInterval(() => this.poll(), 10000);
        },

        scrollBottom() {
            this.$nextTick(() => {
                const el = document.getElementById('chat-stream');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // باکسِ پیام با محتوای چند‌خطی کش می‌آید (سقف با max-height خودِ
        // textarea کنترل می‌شود؛ بعد از آن اسکرولِ داخلی).
        autoGrow() {
            const el = this.$refs.input;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        },

        async send() {
            if (!this.body.trim() || this.sending) return;
            this.sending = true;
            try {
                const res = await fetch('{{ route('crm.tech-chats.send', $technician) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ body: this.body }),
                });
                const json = await res.json();
                if (json.success) {
                    this.appendMessage(json.message);
                    this.body = '';
                    // بعد از خالی شدن، ارتفاعِ باکس به یک خط برگردد.
                    this.$nextTick(() => this.autoGrow());
                }
            } finally {
                this.sending = false;
            }
        },

        async poll() {
            try {
                const res = await fetch('{{ route('crm.tech-chats.poll', $technician) }}?after_id=' + this.lastId, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();
                (json.messages || []).forEach(m => this.appendMessage(m));
            } catch (e) {}
        },

        appendMessage(m) {
            if (m.id <= this.lastId) return;
            this.lastId = m.id;
            const mine = m.sender_type === 'operator';
            const wrap = document.createElement('div');
            wrap.className = 'flex ' + (mine ? 'justify-start' : 'justify-end');
            wrap.dataset.msgId = m.id;
            wrap.innerHTML = `
                <div class="max-w-[78%] ${mine ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200'} rounded-2xl px-3 py-2 text-sm">
                    <div class="whitespace-pre-wrap break-words"></div>
                    <div class="text-[10px] mt-1 opacity-70 text-end">${m.created_at || ''}</div>
                </div>`;
            wrap.querySelector('.whitespace-pre-wrap').textContent = m.body;
            document.getElementById('chat-stream').appendChild(wrap);
            this.scrollBottom();
        },
    };
}
</script>
@endsection
