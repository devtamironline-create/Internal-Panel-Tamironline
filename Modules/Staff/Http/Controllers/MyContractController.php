<?php

namespace Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ActivityLog\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Staff\Models\StaffContract;
use Modules\Staff\Support\ContractSettings;

/**
 * «قرارداد من» — بخشِ خودِ کارمند: مطالعهٔ متن، بارگذاری مدارک،
 * ثبت امضای الکترونیک، ضبط ویدیوی احراز و در نهایت دانلود نسخهٔ تأییدشده.
 *
 * هر کاربر فقط به قراردادهای خودش دسترسی دارد (کنترل در ownedOrFail).
 */
class MyContractController extends Controller
{
    public function index()
    {
        $contracts = StaffContract::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('staff::my.index', compact('contracts'));
    }

    public function show(StaffContract $contract)
    {
        $this->authorizeOwner($contract);

        return view('staff::my.show', [
            'contract' => $contract,
            'party1' => ContractSettings::all(),
            'signatureSrc' => $contract->signature_path
                ? route('my-contracts.file', [$contract, 'signature'])
                : null,
        ]);
    }

    /** بارگذاری یک مدرک (تا ۲۰ مگابایت، بدون محدودیت نوع بیش از تصویر/PDF). */
    public function uploadDocument(Request $request, StaffContract $contract, string $field)
    {
        $this->authorizeOwner($contract);
        $this->assertEditable($contract);
        abort_unless(array_key_exists($field, StaffContract::DOCUMENTS), 404);

        $request->validate([
            'file' => 'required|file|max:'.StaffContract::MAX_UPLOAD_KB.'|mimes:jpg,jpeg,png,webp,heic,pdf',
        ], [
            'file.max' => 'حجم فایل نباید از ۲۰ مگابایت بیشتر باشد.',
            'file.mimes' => 'فرمت مجاز: تصویر (JPG، PNG، WEBP، HEIC) یا PDF.',
        ], ['file' => 'فایل']);

        // فایلِ قبلی همان فیلد پاک می‌شود تا فضای دیسک انباشته نشود.
        if ($contract->{$field}) {
            Storage::disk('public')->delete($contract->{$field});
        }

        $path = $request->file('file')->store('staff-contracts/'.$contract->id.'/documents', 'public');
        $contract->forceFill([$field => $path])->save();

        if ($contract->documentsComplete() && ! $contract->documents_submitted_at) {
            $contract->forceFill(['documents_submitted_at' => now()])->save();
        }

        return back()->with('success', StaffContract::DOCUMENTS[$field].' بارگذاری شد.');
    }

    public function deleteDocument(StaffContract $contract, string $field)
    {
        $this->authorizeOwner($contract);
        $this->assertEditable($contract);
        abort_unless(array_key_exists($field, StaffContract::DOCUMENTS), 404);

        if ($contract->{$field}) {
            Storage::disk('public')->delete($contract->{$field});
            $contract->forceFill([$field => null, 'documents_submitted_at' => null])->save();
        }

        return back()->with('success', 'مدرک حذف شد. می‌توانید نسخهٔ جدید بارگذاری کنید.');
    }

    /** ثبت امضای الکترونیک (تصویر PNG رسم‌شده روی canvas). */
    public function signature(Request $request, StaffContract $contract)
    {
        $this->authorizeOwner($contract);
        $this->assertEditable($contract);

        $validated = $request->validate([
            'signature' => 'required|string',
            'accept_terms' => 'accepted',
        ], [
            'accept_terms.accepted' => 'برای ثبت امضا باید مفاد قرارداد را بپذیرید.',
            'signature.required' => 'امضا ثبت نشده است.',
        ]);

        $binary = $this->decodeDataUri($validated['signature'], ['image/png']);
        if ($binary === null) {
            return back()->withErrors(['signature' => 'تصویر امضا معتبر نیست. دوباره امضا کنید.']);
        }

        if ($contract->signature_path) {
            Storage::disk('public')->delete($contract->signature_path);
        }

        $path = 'staff-contracts/'.$contract->id.'/signature.png';
        Storage::disk('public')->put($path, $binary);

        $contract->forceFill([
            'signature_path' => $path,
            'signed_at' => now(),
            // REMOTE_ADDR مبناست چون هدرهای پراکسی قابل جعل‌اند.
            'signed_ip' => $request->server('REMOTE_ADDR') ?: $request->ip(),
        ])->save();

        return back()->with('success', 'امضای الکترونیک شما ثبت شد.');
    }

    /** ذخیرهٔ ویدیوی احراز که در مرورگر ضبط شده است. */
    public function video(Request $request, StaffContract $contract)
    {
        $this->authorizeOwner($contract);
        $this->assertEditable($contract);

        $request->validate([
            'video' => 'required|file|max:'.StaffContract::MAX_UPLOAD_KB.'|mimetypes:video/webm,video/mp4,video/quicktime,video/x-matroska',
        ], [
            'video.max' => 'حجم ویدیو نباید از ۲۰ مگابایت بیشتر باشد. ضبط کوتاه‌تری انجام دهید.',
            'video.mimetypes' => 'فرمت ویدیو پشتیبانی نمی‌شود.',
        ], ['video' => 'ویدیو']);

        if ($contract->video_path) {
            Storage::disk('public')->delete($contract->video_path);
        }

        $file = $request->file('video');
        $ext = $file->getClientOriginalExtension() ?: 'webm';
        $path = $file->storeAs('staff-contracts/'.$contract->id, 'verification.'.$ext, 'public');

        $contract->forceFill([
            'video_path' => $path,
            'video_recorded_at' => now(),
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'ویدیوی احراز ثبت شد.']);
        }

        return back()->with('success', 'ویدیوی احراز ثبت شد.');
    }

    /** ارسال نهایی برای بررسی ادمین. */
    public function submit(StaffContract $contract)
    {
        $this->authorizeOwner($contract);
        $this->assertEditable($contract);

        if (! $contract->readyToSubmit()) {
            return back()->withErrors([
                'submit' => 'ابتدا همهٔ مدارک، امضای الکترونیک و ویدیوی احراز را تکمیل کنید.',
            ]);
        }

        $contract->fill(['status' => 'submitted', 'reject_reason' => null])->save();

        ActivityLog::record('updated', 'ارسال قرارداد برای بررسی', [
            'entity' => StaffContract::class,
            'entity_label' => 'قرارداد کارمند',
            'entity_id' => $contract->id,
            'entity_title' => $contract->contract_number,
        ]);

        return back()->with('success', 'قرارداد شما برای بررسی ارسال شد. نتیجه از همین صفحه اطلاع‌رسانی می‌شود.');
    }

    /** دانلود نسخهٔ نهاییِ مهر و امضاشده. */
    public function download(StaffContract $contract)
    {
        $this->authorizeOwner($contract);

        abort_unless($contract->status === 'approved'
            && $contract->final_pdf_path
            && Storage::disk('public')->exists($contract->final_pdf_path), 404);

        return Storage::disk('public')->download(
            $contract->final_pdf_path,
            'contract-'.$contract->contract_number.'.pdf'
        );
    }

    /** سروِ محافظت‌شدهٔ فایل‌های خودِ کارمند (مدرک/امضا/ویدیو). */
    public function file(StaffContract $contract, string $field)
    {
        $this->authorizeOwner($contract);

        $path = match ($field) {
            'signature' => $contract->signature_path,
            'video' => $contract->video_path,
            default => array_key_exists($field, StaffContract::DOCUMENTS) ? $contract->{$field} : null,
        };

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(storage_path('app/public/'.$path));
    }

    private function authorizeOwner(StaffContract $contract): void
    {
        abort_unless((int) $contract->user_id === (int) auth()->id(), 403, 'این قرارداد متعلق به شما نیست.');
    }

    private function assertEditable(StaffContract $contract): void
    {
        abort_unless(
            $contract->editableByStaff(),
            403,
            'این قرارداد در وضعیت «'.$contract->statusLabel().'» است و قابل ویرایش نیست.'
        );
    }

    /**
     * رمزگشاییِ امنِ data-uri — فقط نوع‌های مجاز و با اندازهٔ محدود.
     *
     * @param  list<string>  $allowedMimes
     */
    private function decodeDataUri(string $dataUri, array $allowedMimes): ?string
    {
        if (! preg_match('#^data:([\w/\-\.\+]+);base64,(.+)$#s', trim($dataUri), $m)) {
            return null;
        }
        if (! in_array(strtolower($m[1]), $allowedMimes, true)) {
            return null;
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || $binary === '') {
            return null;
        }
        // سقف ۵ مگابایت برای تصویر امضا (بسیار بیشتر از نیاز واقعی).
        if (strlen($binary) > 5 * 1024 * 1024) {
            return null;
        }

        // تأیید اینکه واقعاً تصویر است (نه فایلِ جعلی با prefix تصویر).
        return @imagecreatefromstring($binary) === false ? null : $binary;
    }
}
