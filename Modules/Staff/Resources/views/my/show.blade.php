@extends('layouts.admin')

@section('page-title', 'تکمیل قرارداد')

@section('main')
@php use Modules\Staff\Models\StaffContract; use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4 max-w-5xl mx-auto" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">📄 قرارداد شما</h1>
            <p class="text-sm text-gray-500 mt-1" dir="ltr">{{ $contract->contract_number }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($contract->status === 'approved' && $contract->final_pdf_path)
                <a href="{{ route('my-contracts.download', $contract) }}"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">⬇️ دانلود قرارداد امضاشده (PDF)</a>
            @endif
            <a href="{{ route('my-contracts.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">بازگشت</a>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    @if($contract->status === 'approved')
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-lg p-4 text-sm">
            ✅ قرارداد شما توسط مدیریت تأیید و امضا شده است. نسخهٔ نهایی با مهر و امضای مجموعه از دکمهٔ بالا قابل دانلود است.
        </div>
    @elseif($contract->status === 'submitted')
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 rounded-lg p-4 text-sm">
            ⏳ مدارک و امضای شما ارسال شد و در انتظار بررسی مدیریت است. تا اعلام نتیجه امکان ویرایش وجود ندارد.
        </div>
    @elseif($contract->status === 'rejected')
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-lg p-4 text-sm">
            <b>قرارداد شما نیازمند اصلاح است:</b> {{ $contract->reject_reason }}
            <div class="text-[11px] mt-1">پس از اصلاح موارد بالا، دوباره دکمهٔ «ارسال برای بررسی» را بزنید.</div>
        </div>
    @endif

    {{-- نوار پیشرفت --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        @php($p = $contract->completionPercent())
        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
            <span>پیشرفت تکمیل</span><span dir="ltr">{{ $p }}%</span>
        </div>
        <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
            <div @class(['h-full rounded-full transition-all', 'bg-emerald-500' => $p === 100, 'bg-amber-500' => $p < 100]) style="width: {{ $p }}%"></div>
        </div>
        <div class="flex flex-wrap gap-2 mt-3 text-[11px]">
            <span @class(['px-2 py-1 rounded-full', 'bg-emerald-100 text-emerald-700' => $contract->documentsComplete(), 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! $contract->documentsComplete()])>
                {{ $contract->documentsComplete() ? '✓' : '○' }} مدارک ({{ count($contract->uploadedDocuments()) }} از {{ count(StaffContract::DOCUMENTS) }})
            </span>
            <span @class(['px-2 py-1 rounded-full', 'bg-emerald-100 text-emerald-700' => $contract->hasSignature(), 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! $contract->hasSignature()])>
                {{ $contract->hasSignature() ? '✓' : '○' }} امضای الکترونیک
            </span>
            <span @class(['px-2 py-1 rounded-full', 'bg-emerald-100 text-emerald-700' => $contract->hasVideo(), 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! $contract->hasVideo()])>
                {{ $contract->hasVideo() ? '✓' : '○' }} ویدیوی احراز
            </span>
        </div>
    </div>

    {{-- ۱) متن قرارداد --}}
    <details class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700" open>
        <summary class="p-4 font-bold text-sm text-gray-800 dark:text-gray-100 cursor-pointer">۱) متن کامل قرارداد — لطفاً با دقت مطالعه کنید</summary>
        <div class="px-6 pb-6">
            <div class="prose-contract text-sm text-gray-700 dark:text-gray-200 leading-7 max-h-[60vh] overflow-y-auto border border-gray-100 dark:border-gray-700 rounded-lg p-4">
                @include($contract->documentView(), [
                    'contract' => $contract, 'party1' => $party1, 'forPdf' => false, 'signatureSrc' => $signatureSrc,
                ])
            </div>
        </div>
    </details>

    @if($contract->editableByStaff())
        {{-- ۲) مدارک --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-1">۲) بارگذاری مدارک</h2>
            <p class="text-[11px] text-gray-400 mb-3">فرمت‌های مجاز: تصویر (JPG، PNG، WEBP، HEIC) یا PDF — حداکثر حجم هر فایل <b>۲۰ مگابایت</b>.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach(StaffContract::DOCUMENTS as $field => $label)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ $label }}</div>
                                @if(isset(StaffContract::DOCUMENT_HINTS[$field]))
                                    <div class="text-[10px] text-gray-400 mt-0.5">{{ StaffContract::DOCUMENT_HINTS[$field] }}</div>
                                @endif
                            </div>
                            @if($contract->{$field})
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 whitespace-nowrap">بارگذاری شد</span>
                            @endif
                        </div>

                        @if($contract->{$field})
                            <div class="flex items-center gap-2 mt-2">
                                <a href="{{ route('my-contracts.file', [$contract, $field]) }}" target="_blank" class="text-[11px] text-brand-600 hover:underline">مشاهده ↗</a>
                                <form method="POST" action="{{ route('my-contracts.documents.delete', [$contract, $field]) }}"
                                      onsubmit="return confirm('این مدرک حذف شود؟');">
                                    @csrf @method('DELETE')
                                    <button class="text-[11px] text-red-600 hover:underline">حذف و بارگذاری مجدد</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('my-contracts.documents.upload', [$contract, $field]) }}"
                                  enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
                                @csrf
                                <input type="file" name="file" required accept="image/*,.pdf,.heic"
                                       class="flex-1 text-[11px] text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:bg-brand-50 file:text-brand-700">
                                <button class="px-3 py-1.5 bg-brand-600 text-white rounded-lg text-[11px] whitespace-nowrap">بارگذاری</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ۳) امضای الکترونیک --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
             x-data="signaturePad()" x-init="init()">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-1">۳) امضای الکترونیک</h2>
            <p class="text-[11px] text-gray-400 mb-3">امضای خود را با موس یا انگشت در کادر زیر بکشید.</p>

            @if($contract->hasSignature())
                <div class="text-center mb-3">
                    <img src="{{ $signatureSrc }}" alt="امضای ثبت‌شده" class="max-h-24 mx-auto bg-white rounded border border-gray-200 p-2">
                    <p class="text-[11px] text-emerald-600 mt-1">امضای شما در {{ Jalalian::fromDateTime($contract->signed_at)->format('Y/m/d H:i') }} ثبت شد. برای تغییر، دوباره امضا کنید.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('my-contracts.signature', $contract) }}" @submit="prepare($event)">
                @csrf
                <input type="hidden" name="signature" x-ref="data">
                <canvas x-ref="canvas" class="w-full bg-white border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg touch-none" style="height:180px;"></canvas>
                <label class="flex items-start gap-2 mt-3 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span>متن کامل قرارداد را مطالعه کردم، مفاد آن را می‌پذیرم و این امضا را با آگاهی و اختیار کامل ثبت می‌کنم.</span>
                </label>
                <div class="flex items-center gap-2 mt-3">
                    <button type="submit" :disabled="empty" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 disabled:opacity-40 text-white rounded-lg text-sm font-bold">ثبت امضا</button>
                    <button type="button" @click="clear()" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm">پاک‌کردن</button>
                </div>
            </form>
        </div>

        {{-- ۴) ویدیوی احراز --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
             x-data="videoRecorder('{{ route('my-contracts.video', $contract) }}')">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-1">۴) ویدیوی احراز امضا</h2>
            <div class="text-[11px] text-gray-500 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-3 leading-6">
                لطفاً رو به دوربین این جمله را بگویید:
                <b class="block mt-1 text-amber-800 dark:text-amber-300">
                    «اینجانب {{ $contract->party_name }} با کد ملی {{ $contract->party_national_code ?: '—' }}،
                    قرارداد شمارهٔ {{ $contract->contract_number }} را مطالعه کردم و با آگاهی کامل امضا می‌کنم.»
                </b>
                <span class="block mt-1">حداکثر حجم ویدیو ۲۰ مگابایت است؛ ضبط حدود ۱۰ تا ۲۰ ثانیه کافی است.</span>
            </div>

            @if($contract->hasVideo())
                <div class="mb-3">
                    <video controls preload="metadata" class="w-full rounded-lg bg-black max-h-64"
                           src="{{ route('my-contracts.file', [$contract, 'video']) }}"></video>
                    <p class="text-[11px] text-emerald-600 mt-1 text-center">ویدیو در {{ Jalalian::fromDateTime($contract->video_recorded_at)->format('Y/m/d H:i') }} ثبت شد. برای تغییر، دوباره ضبط کنید.</p>
                </div>
            @endif

            <video x-ref="preview" autoplay muted playsinline class="w-full rounded-lg bg-black max-h-64" x-show="streaming || recordedUrl" x-cloak></video>

            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button type="button" @click="start()" x-show="!streaming && !recordedUrl"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm">🎥 شروع ضبط</button>
                <button type="button" @click="stop()" x-show="streaming" x-cloak
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">⏹️ پایان ضبط (<span x-text="seconds"></span> ثانیه)</button>
                <template x-if="recordedUrl">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="upload()" :disabled="uploading"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white rounded-lg text-sm">
                            <span x-show="!uploading">⬆️ ارسال ویدیو</span>
                            <span x-show="uploading" x-cloak>در حال ارسال…</span>
                        </button>
                        <button type="button" @click="reset()" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm">ضبط دوباره</button>
                    </div>
                </template>
            </div>
            <p class="text-[11px] mt-2" :class="error ? 'text-red-600' : 'text-gray-400'" x-text="message"></p>

            <noscript><p class="text-[11px] text-red-600 mt-2">برای ضبط ویدیو باید جاوااسکریپت مرورگر فعال باشد.</p></noscript>
        </div>

        {{-- ۵) ارسال نهایی --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-2">۵) ارسال برای بررسی</h2>
            @if($contract->readyToSubmit())
                <form method="POST" action="{{ route('my-contracts.submit', $contract) }}"
                      onsubmit="return confirm('قرارداد برای بررسی مدیریت ارسال شود؟ پس از ارسال تا اعلام نتیجه امکان ویرایش نیست.');">
                    @csrf
                    <button class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold">
                        ✅ ارسال قرارداد برای بررسی مدیریت
                    </button>
                </form>
            @else
                <p class="text-xs text-gray-500">برای ارسال، ابتدا این موارد را تکمیل کنید:</p>
                <ul class="list-disc pr-5 mt-2 space-y-1 text-[11px] text-red-600">
                    @foreach($contract->missingDocuments() as $label)<li>{{ $label }}</li>@endforeach
                    @if(! $contract->hasSignature())<li>ثبت امضای الکترونیک</li>@endif
                    @if(! $contract->hasVideo())<li>ضبط ویدیوی احراز امضا</li>@endif
                </ul>
            @endif
        </div>
    @endif
</div>

<style>
    .prose-contract h2 { font-size: 0.95rem; font-weight: 700; margin: 1rem 0 0.4rem; padding-bottom: 0.2rem; border-bottom: 1px solid rgba(128,128,128,.25); }
    .prose-contract p { margin-bottom: 0.4rem; text-align: justify; }
</style>

<script>
// ── پد امضا: رسم با موس/لمس روی canvas و ارسال به‌صورت data-uri ──
function signaturePad() {
    return {
        empty: true, ctx: null, drawing: false,
        init() {
            const c = this.$refs.canvas;
            // تطبیق رزولوشن با devicePixelRatio تا امضا در PDF تار نشود
            const ratio = window.devicePixelRatio || 1;
            c.width = c.offsetWidth * ratio;
            c.height = c.offsetHeight * ratio;
            this.ctx = c.getContext('2d');
            this.ctx.scale(ratio, ratio);
            this.ctx.lineWidth = 2.2;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.strokeStyle = '#111827';

            const pos = (e) => {
                const r = c.getBoundingClientRect();
                const p = e.touches ? e.touches[0] : e;
                return { x: p.clientX - r.left, y: p.clientY - r.top };
            };
            const down = (e) => { e.preventDefault(); this.drawing = true; const { x, y } = pos(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); };
            const move = (e) => { if (!this.drawing) return; e.preventDefault(); const { x, y } = pos(e); this.ctx.lineTo(x, y); this.ctx.stroke(); this.empty = false; };
            const up = () => { this.drawing = false; };

            c.addEventListener('mousedown', down); c.addEventListener('mousemove', move);
            window.addEventListener('mouseup', up);
            c.addEventListener('touchstart', down, { passive: false });
            c.addEventListener('touchmove', move, { passive: false });
            window.addEventListener('touchend', up);
        },
        clear() {
            const c = this.$refs.canvas;
            this.ctx.clearRect(0, 0, c.width, c.height);
            this.empty = true;
        },
        prepare(e) {
            if (this.empty) { e.preventDefault(); return; }
            // پس‌زمینهٔ سفید زیر امضا تا در PDF شفاف/سیاه نشود
            const c = this.$refs.canvas;
            const out = document.createElement('canvas');
            out.width = c.width; out.height = c.height;
            const o = out.getContext('2d');
            o.fillStyle = '#ffffff'; o.fillRect(0, 0, out.width, out.height);
            o.drawImage(c, 0, 0);
            this.$refs.data.value = out.toDataURL('image/png');
        },
    };
}

// ── ضبط زندهٔ ویدیو در مرورگر (MediaRecorder) ──
function videoRecorder(uploadUrl) {
    return {
        streaming: false, uploading: false, error: false,
        message: 'برای شروع، دکمهٔ «شروع ضبط» را بزنید و اجازهٔ دسترسی به دوربین را بدهید.',
        seconds: 0, recordedUrl: null,
        stream: null, recorder: null, chunks: [], blob: null, timer: null,
        async start() {
            this.error = false;
            if (!navigator.mediaDevices || !window.MediaRecorder) {
                this.error = true;
                this.message = 'مرورگر شما از ضبط ویدیو پشتیبانی نمی‌کند. لطفاً از مرورگر به‌روز (کروم/سافاری) استفاده کنید.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: true,
                });
            } catch (e) {
                this.error = true;
                this.message = 'دسترسی به دوربین/میکروفن داده نشد. از تنظیمات مرورگر اجازه دهید و دوباره تلاش کنید.';
                return;
            }
            this.$refs.preview.srcObject = this.stream;
            this.$refs.preview.muted = true;
            this.chunks = [];
            // انتخاب اولین فرمت پشتیبانی‌شده؛ سافاری معمولاً mp4 می‌دهد.
            const types = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
            const mime = types.find(t => MediaRecorder.isTypeSupported(t)) || '';
            this.recorder = new MediaRecorder(this.stream, mime ? { mimeType: mime, videoBitsPerSecond: 800000 } : {});
            this.recorder.ondataavailable = (e) => { if (e.data.size > 0) this.chunks.push(e.data); };
            this.recorder.onstop = () => {
                this.blob = new Blob(this.chunks, { type: this.recorder.mimeType || 'video/webm' });
                this.recordedUrl = URL.createObjectURL(this.blob);
                this.$refs.preview.srcObject = null;
                this.$refs.preview.src = this.recordedUrl;
                this.$refs.preview.muted = false;
                this.$refs.preview.controls = true;
                const mb = (this.blob.size / 1048576).toFixed(1);
                this.message = 'ضبط پایان یافت (' + mb + ' مگابایت). پیش‌نمایش را ببینید و اگر مناسب بود ارسال کنید.';
                if (this.blob.size > 20 * 1048576) {
                    this.error = true;
                    this.message = 'حجم ویدیو ' + mb + ' مگابایت است و از سقف ۲۰ مگابایت بیشتر است. لطفاً ضبط کوتاه‌تری انجام دهید.';
                }
            };
            this.recorder.start();
            this.streaming = true;
            this.seconds = 0;
            this.message = 'در حال ضبط… جملهٔ بالا را رو به دوربین بگویید.';
            this.timer = setInterval(() => {
                this.seconds++;
                if (this.seconds >= 60) this.stop(); // سقف ایمن یک دقیقه
            }, 1000);
        },
        stop() {
            if (this.timer) clearInterval(this.timer);
            if (this.recorder && this.recorder.state !== 'inactive') this.recorder.stop();
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());
            this.streaming = false;
        },
        reset() {
            this.recordedUrl = null; this.blob = null; this.chunks = [];
            this.$refs.preview.src = ''; this.$refs.preview.controls = false;
            this.error = false;
            this.message = 'برای شروع، دکمهٔ «شروع ضبط» را بزنید.';
        },
        async upload() {
            if (!this.blob) return;
            if (this.blob.size > 20 * 1048576) {
                this.error = true;
                this.message = 'حجم ویدیو بیش از ۲۰ مگابایت است. لطفاً دوباره و کوتاه‌تر ضبط کنید.';
                return;
            }
            this.uploading = true; this.error = false;
            this.message = 'در حال ارسال ویدیو…';
            const ext = (this.blob.type || '').includes('mp4') ? 'mp4' : 'webm';
            const form = new FormData();
            form.append('video', this.blob, 'verification.' + ext);
            form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            try {
                const res = await fetch(uploadUrl, {
                    method: 'POST', body: form,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('upload failed');
                this.message = 'ویدیو با موفقیت ثبت شد. صفحه به‌روزرسانی می‌شود…';
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                this.error = true;
                this.uploading = false;
                this.message = 'ارسال ویدیو ناموفق بود. اینترنت را بررسی کنید و دوباره تلاش کنید.';
            }
        },
    };
}
</script>
@endsection
