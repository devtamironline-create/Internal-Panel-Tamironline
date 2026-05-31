@extends('crm::tech-panel.layout')

@section('title', 'پیام‌ها')

@section('body')
<div class="min-h-screen pb-nav flex flex-col" style="background: #eef0f4;">
    {{-- ─────── Hero ─────── --}}
    <div class="relative overflow-hidden rounded-b-[28px] pt-5 pb-5 px-4"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">گفت‌وگو با پشتیبانی</div>
            <div class="w-10"></div>
        </div>
        @if($operators->isNotEmpty())
            {{-- نمایش هویت یکپارچهٔ «پشتیبانی» — نام واقعی اپراتورها از
                 تکنسین مخفی می‌ماند تا تجربه‌اش تجاری/استاندارد باشد. --}}
            <div class="mt-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-white/15 flex items-center justify-center text-white font-bold">
                    پ
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white font-bold text-sm truncate">پشتیبانی تعمیرآنلاین</div>
                    <div class="text-[11px] text-white/70">تیم کارشناسی</div>
                </div>
            </div>
        @endif
    </div>

    @if($operators->isEmpty())
        <div class="mx-3 mt-4 bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-900 leading-7 text-center">
            هنوز کارشناسی برای شما تخصیص داده نشده است. لطفاً منتظر بمانید — به‌محض تخصیص، گفت‌وگو فعال می‌شود.
        </div>
    @else
        <div x-data="techMessages()" x-init="init()" class="flex-1 flex flex-col px-3 mt-3">
            {{-- پیام‌ها --}}
            <div id="msg-stream" class="flex-1 overflow-y-auto space-y-2 pb-2" style="min-height: 50vh;">
                @foreach($messages as $msg)
                    @php $mine = $msg->sender_type === \Modules\CRM\Models\TechChatMessage::SENDER_TECH; @endphp
                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%] {{ $mine ? 'bg-brand-600 text-white rounded-2xl rounded-bl-md' : 'bg-white border border-gray-200 rounded-2xl rounded-br-md' }} px-3 py-2 text-sm shadow-sm">
                            <div class="whitespace-pre-wrap break-words">{{ $msg->body }}</div>
                            <div class="text-[10px] mt-1 opacity-70 text-end">@jdatetime($msg->created_at)</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- فرم ارسال --}}
            <form @submit.prevent="send()" class="sticky bottom-[80px] bg-white rounded-2xl shadow p-2 flex items-end gap-2 mt-2">
                <textarea x-model="body" rows="1" placeholder="پیامتان را بنویسید…"
                          class="flex-1 resize-none border-0 focus:outline-none text-sm px-2 py-2 bg-transparent"
                          style="max-height: 100px;"></textarea>
                <button type="submit" :disabled="!body.trim() || sending"
                        class="w-10 h-10 rounded-full bg-brand-600 text-white flex items-center justify-center disabled:opacity-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </form>
        </div>

        <script>
        function techMessages() {
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
                        const el = document.getElementById('msg-stream');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },
                async send() {
                    if (!this.body.trim() || this.sending) return;
                    this.sending = true;
                    try {
                        const res = await fetch('{{ route('tech.messages.send') }}', {
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
                            this.append(json.message);
                            this.body = '';
                        }
                    } finally {
                        this.sending = false;
                    }
                },
                async poll() {
                    try {
                        const res = await fetch('{{ route('tech.messages.poll') }}?after_id=' + this.lastId);
                        const json = await res.json();
                        (json.messages || []).forEach(m => this.append(m));
                    } catch (e) {}
                },
                append(m) {
                    if (m.id <= this.lastId) return;
                    this.lastId = m.id;
                    const mine = m.sender_type === 'tech';
                    const wrap = document.createElement('div');
                    wrap.className = 'flex ' + (mine ? 'justify-end' : 'justify-start');
                    wrap.innerHTML = `
                        <div class="max-w-[78%] ${mine ? 'bg-brand-600 text-white rounded-2xl rounded-bl-md' : 'bg-white border border-gray-200 rounded-2xl rounded-br-md'} px-3 py-2 text-sm shadow-sm">
                            <div class="whitespace-pre-wrap break-words"></div>
                            <div class="text-[10px] mt-1 opacity-70 text-end">${m.created_at || ''}</div>
                        </div>`;
                    wrap.querySelector('.whitespace-pre-wrap').textContent = m.body;
                    document.getElementById('msg-stream').appendChild(wrap);
                    this.scrollBottom();
                },
            };
        }
        </script>
    @endif
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.messages'])
@endsection
