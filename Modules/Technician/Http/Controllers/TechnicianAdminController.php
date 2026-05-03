<?php

namespace Modules\Technician\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CRM\Models\Technician as CrmTechnician;
use Modules\SMS\Services\KavenegarService;
use Modules\Technician\Models\ApplianceCategory;
use Modules\Technician\Models\TechnicianRegistration;
use Modules\Technician\Models\TechnicianRegistrationLog;
use Modules\Technician\Models\TechnicianSetting;

class TechnicianAdminController extends Controller
{
    private function checkAccess(): void
    {
        if (!auth()->user()->can('manage-technicians') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }
    }

    /**
     * دسترسی ویرایش اطلاعات (نه تأیید/رد).
     * - manage-permissions: super-admin (همیشه مجاز)
     * - edit-technician-registration: permission اختصاصی ویرایش
     * - manage-technicians: دسترسی کلی (همیشه مجاز)
     */
    private function checkEditAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            !$u->can('edit-technician-registration')
            && !$u->can('manage-technicians')
            && !$u->can('manage-permissions')
        )) {
            abort(403, 'برای ویرایش اطلاعات این درخواست دسترسی ندارید.');
        }
    }

    /**
     * صفحه تنظیمات لندینگ
     */
    public function settings()
    {
        $this->checkAccess();

        $defaults = TechnicianSetting::defaults();

        $settings = [
            'page_title'         => TechnicianSetting::get('page_title',         $defaults['page_title']),
            'brand_name'         => TechnicianSetting::get('brand_name',          $defaults['brand_name']),
            'brand_logo'         => TechnicianSetting::get('brand_logo',          null),
            'hero_title'         => TechnicianSetting::get('hero_title',          $defaults['hero_title']),
            'hero_subtitle'      => TechnicianSetting::get('hero_subtitle',       $defaults['hero_subtitle']),
            'hero_description'   => TechnicianSetting::get('hero_description',    $defaults['hero_description']),
            'hero_cta_text'      => TechnicianSetting::get('hero_cta_text',       $defaults['hero_cta_text']),
            'hero_cta_link'      => TechnicianSetting::get('hero_cta_link',       $defaults['hero_cta_link']),
            'hero_secondary_text' => TechnicianSetting::get('hero_secondary_text', $defaults['hero_secondary_text']),
            'hero_secondary_link' => TechnicianSetting::get('hero_secondary_link', $defaults['hero_secondary_link']),
            'hero_badge'         => TechnicianSetting::get('hero_badge',          $defaults['hero_badge']),
            'hero_bg_image'      => TechnicianSetting::get('hero_bg_image',        null),
            'hero_overlay_color' => TechnicianSetting::get('hero_overlay_color',   '#0f2a4a'),
            'hero_overlay_opacity' => TechnicianSetting::get('hero_overlay_opacity', '60'),
            'benefits_title'     => TechnicianSetting::get('benefits_title',      $defaults['benefits_title']),
            'benefits_json'      => TechnicianSetting::get('benefits',            $defaults['benefits']),
            'steps_title'        => TechnicianSetting::get('steps_title',         $defaults['steps_title']),
            'steps_json'         => TechnicianSetting::get('steps',               $defaults['steps']),
            'requirements_title' => TechnicianSetting::get('requirements_title',  $defaults['requirements_title']),
            'requirements_json'  => TechnicianSetting::get('requirements',        $defaults['requirements']),
            'faq_title'          => TechnicianSetting::get('faq_title',           $defaults['faq_title']),
            'faq_json'           => TechnicianSetting::get('faq',                 $defaults['faq']),
            'cta_title'          => TechnicianSetting::get('cta_title',           $defaults['cta_title']),
            'cta_description'    => TechnicianSetting::get('cta_description',     $defaults['cta_description']),
            'cta_badge'          => TechnicianSetting::get('cta_badge',           $defaults['cta_badge']),
            'cta_button_text'    => TechnicianSetting::get('cta_button_text',     $defaults['cta_button_text']),
            'cta_button_link'    => TechnicianSetting::get('cta_button_link',     $defaults['cta_button_link']),
            'cta_phone_text'     => TechnicianSetting::get('cta_phone_text',      $defaults['cta_phone_text']),
            'cta_phone'          => TechnicianSetting::get('cta_phone',           $defaults['cta_phone']),
            'cta_footnote'       => TechnicianSetting::get('cta_footnote',        $defaults['cta_footnote']),
            'contract_text'      => TechnicianSetting::get('contract_text',       ''),
            'contract_sms_template' => TechnicianSetting::get('contract_sms_template', ''),
            'default_commission_percent' => TechnicianSetting::get('default_commission_percent', ''),
            'default_promissory_note_amount' => TechnicianSetting::get('default_promissory_note_amount', ''),
            'sms_approved_template' => TechnicianSetting::get('sms_approved_template', ''),
            'sms_rejected_template' => TechnicianSetting::get('sms_rejected_template', ''),
            'sms_biometric_submitted_template' => TechnicianSetting::get('sms_biometric_submitted_template', ''),
            'sms_documents_rejected_template' => TechnicianSetting::get('sms_documents_rejected_template', ''),
            'sms_contract_rejected_template' => TechnicianSetting::get('sms_contract_rejected_template', ''),
        ];

        return view('technician::admin.settings', compact('settings'));
    }

    /**
     * ذخیره تنظیمات لندینگ
     */
    public function updateSettings(Request $request)
    {
        $this->checkAccess();

        $simpleFields = [
            'page_title', 'brand_name',
            'hero_title', 'hero_subtitle', 'hero_description',
            'hero_cta_text', 'hero_cta_link', 'hero_secondary_text', 'hero_secondary_link',
            'hero_badge', 'hero_overlay_color', 'hero_overlay_opacity',
            'benefits_title', 'steps_title', 'requirements_title', 'faq_title',
            'cta_title', 'cta_description', 'cta_badge',
            'cta_button_text', 'cta_button_link', 'cta_phone_text', 'cta_phone', 'cta_footnote',
            'contract_text', 'contract_sms_template',
            'default_commission_percent', 'default_promissory_note_amount',
            'sms_approved_template', 'sms_rejected_template', 'sms_biometric_submitted_template',
            'sms_documents_rejected_template', 'sms_contract_rejected_template',
        ];

        foreach ($simpleFields as $field) {
            if ($request->has($field)) {
                TechnicianSetting::set($field, $request->input($field));
            }
        }

        // آپلود تصویر پس‌زمینه هیرو
        if ($request->hasFile('hero_bg_image')) {
            $file = $request->file('hero_bg_image');
            if ($file->isValid() && in_array($file->getClientMimeType(), [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            ]) && $file->getSize() <= 5 * 1024 * 1024) {
                $old = TechnicianSetting::get('hero_bg_image');
                if ($old) Storage::disk('public')->delete($old);
                $path = $file->store('technician', 'public');
                TechnicianSetting::set('hero_bg_image', $path);
            }
        }

        // آپلود لوگوی صفحه عمومی
        if ($request->hasFile('brand_logo')) {
            $file = $request->file('brand_logo');
            if ($file->isValid() && in_array($file->getClientMimeType(), [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'image/svg+xml', 'image/bmp',
            ]) && $file->getSize() <= 2 * 1024 * 1024) {
                $old = TechnicianSetting::get('brand_logo');
                if ($old) Storage::disk('public')->delete($old);
                $path = $file->store('technician', 'public');
                TechnicianSetting::set('brand_logo', $path);
            }
        }

        // فیلدهای JSON
        $jsonFields = ['benefits', 'steps', 'requirements', 'faq'];
        foreach ($jsonFields as $field) {
            if ($request->has($field . '_json')) {
                $raw = $request->input($field . '_json');
                // اعتبارسنجی JSON
                json_decode($raw);
                if (json_last_error() === JSON_ERROR_NONE) {
                    TechnicianSetting::set($field, $raw);
                }
            }
        }

        return redirect()->route('technician.admin.settings')
            ->with('success', 'تنظیمات صفحه جذب تکنسین با موفقیت ذخیره شد.');
    }

    /**
     * حذف تصویر پس‌زمینه هیرو
     */
    public function deleteHeroBg()
    {
        $this->checkAccess();
        $old = TechnicianSetting::get('hero_bg_image');
        if ($old) Storage::disk('public')->delete($old);
        TechnicianSetting::set('hero_bg_image', null);

        return redirect()->route('technician.admin.settings')
            ->with('success', 'تصویر پس‌زمینه هیرو حذف شد.');
    }

    /**
     * حذف لوگوی صفحه عمومی
     */
    public function deleteLogo()
    {
        $this->checkAccess();
        $old = TechnicianSetting::get('brand_logo');
        if ($old) Storage::disk('public')->delete($old);
        TechnicianSetting::set('brand_logo', null);

        return redirect()->route('technician.admin.settings')
            ->with('success', 'لوگو حذف شد.');
    }

    /**
     * ریست به مقادیر پیش‌فرض
     */
    public function resetDefaults()
    {
        $this->checkAccess();

        foreach (TechnicianSetting::defaults() as $key => $value) {
            TechnicianSetting::set($key, $value);
        }

        return redirect()->route('technician.admin.settings')
            ->with('success', 'تنظیمات به مقادیر پیش‌فرض بازگشت.');
    }

    /**
     * لیست درخواست‌های ثبت‌نام تکنسین
     */
    public function registrations(Request $request)
    {
        $this->checkAccess();

        // ابتدا یک closure از فیلترهای غیر-status می‌سازیم تا هم برای
        // واکشی اصلی استفاده کنیم و هم برای شمارش هر تب وضعیت.
        $applyNonStatusFilters = function ($q) use ($request) {
            if ($request->filled('search')) {
                $search = $request->search;
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('mobile', 'like', "%{$search}%")
                       ->orWhere('national_code', 'like', "%{$search}%");
                });
            }
            if ($request->filled('location')) {
                $loc = $request->location;
                $q->where(function ($qq) use ($loc) {
                    $qq->where('province', 'like', "%{$loc}%")
                       ->orWhere('city', 'like', "%{$loc}%");
                });
            }
            if ($request->filled('activity_type')) {
                $q->where('activity_type', $request->activity_type);
            }
            if ($request->filled('appliance')) {
                $q->whereJsonContains('appliance_categories', (int) $request->appliance);
            }
        };

        $query = TechnicianRegistration::query()->orderByDesc('created_at');
        $applyNonStatusFilters($query);

        // فیلتر وضعیت — به‌صورت پیش‌فرض، رکوردهای بایگانی‌شده نمایش داده
        // نمی‌شوند مگر اینکه ادمین صریحاً status=archived را فیلتر کند.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'archived');
        }

        $registrations = $query->paginate(20)->withQueryString();

        // ─── شمارش هر وضعیت برای تب‌ها (با اعمال بقیهٔ فیلترها) ───
        $statusCountQuery = TechnicianRegistration::query();
        $applyNonStatusFilters($statusCountQuery);

        $rawCounts = (clone $statusCountQuery)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $statusCounts = [
            'all'        => array_sum(array_diff_key($rawCounts, ['archived' => true])),
            'incomplete' => (int) ($rawCounts['incomplete'] ?? 0),
            'pending'    => (int) ($rawCounts['pending']    ?? 0),
            'approved'   => (int) ($rawCounts['approved']   ?? 0),
            'rejected'   => (int) ($rawCounts['rejected']   ?? 0),
            'archived'   => (int) ($rawCounts['archived']   ?? 0),
        ];

        // داده‌های کمکی برای ستون‌ها و فیلترها
        $appliances = ApplianceCategory::orderBy('name')->get(['id', 'name']);
        $applianceMap = $appliances->pluck('name', 'id')->all();
        $activityTypes = TechnicianRegistration::query()
            ->whereNotNull('activity_type')
            ->where('activity_type', '!=', '')
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type')
            ->all();

        return view('technician::admin.registrations', compact(
            'registrations', 'appliances', 'applianceMap', 'activityTypes', 'statusCounts'
        ));
    }

    /**
     * جزئیات درخواست ثبت‌نام
     */
    public function registrationShow($id)
    {
        $this->checkAccess();

        $registration = TechnicianRegistration::with(['logs.changedBy:id,first_name'])->findOrFail($id);

        // واکشی نام دستگاه‌ها از دیتابیس
        $applianceNames = [];
        if (!empty($registration->appliance_categories)) {
            $applianceNames = ApplianceCategory::whereIn('id', $registration->appliance_categories)
                ->pluck('name')
                ->toArray();
        }

        return view('technician::admin.registration-show', compact('registration', 'applianceNames'));
    }

    /**
     * تغییر وضعیت درخواست ثبت‌نام
     */
    public function registrationUpdateStatus(Request $request, $id)
    {
        if (!auth()->user()->can('approve-technician') && !auth()->user()->can('manage-technicians') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'status'           => ['required', 'in:pending,approved,rejected,archived'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
            'archive_reason'   => ['nullable', 'string', 'max:1000'],
        ], [
            'rejection_reason.required_if' => 'لطفاً دلیل رد درخواست را وارد کنید.',
        ]);

        $registration = TechnicianRegistration::findOrFail($id);

        $previousStatus = $registration->status;
        $data = ['status' => $request->status];

        if ($request->status === 'rejected') {
            $data['rejection_reason'] = $request->rejection_reason;
        } elseif ($request->status === 'archived') {
            $data['archive_reason'] = $request->archive_reason;
            $data['archived_at']    = now();
        } else {
            // برگرداندن از حالت‌های rejected/archived — متادیتا را پاک کن
            $data['rejection_reason'] = null;
            $data['archive_reason']   = null;
            $data['archived_at']      = null;
        }

        $registration->update($data);

        // ─── لاگ: تغییر وضعیت ───────────────────────────────────────
        if ($request->status === 'archived') {
            $this->logActivity($registration, 'archive', $previousStatus, 'archived', $request->archive_reason);
        } elseif ($previousStatus === 'archived' && $request->status !== 'archived') {
            $this->logActivity($registration, 'unarchive', 'archived', $request->status);
        } else {
            $this->logActivity(
                $registration,
                'status_change',
                $previousStatus,
                $request->status,
                $request->status === 'rejected' ? $request->rejection_reason : null
            );
        }

        // ارسال پیامک اطلاع‌رسانی (برای archived پیامک نمی‌زنیم — ممکن است
        // در آینده فعال شود و نمی‌خواهیم تکنسین را ناامید کنیم)
        if ($request->status !== 'archived') {
            $this->sendStatusSms($registration, $request->status);
        }

        $statusLabels = [
            'pending'  => 'در انتظار بررسی',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            'archived' => 'بایگانی شده',
        ];

        return redirect()->route('technician.admin.registrations.show', $id)
            ->with('success', 'وضعیت به «' . $statusLabels[$request->status] . '» تغییر یافت.');
    }

    /**
     * تغییر مرحله ثبت‌نام
     */
    public function registrationUpdateStep(Request $request, $id)
    {
        $this->checkAccess();
        $this->checkEditAccess();

        $request->validate([
            'current_step' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'current_step.required' => 'مرحله الزامی است.',
            'current_step.min'      => 'مرحله نمی‌تواند کمتر از ۱ باشد.',
            'current_step.max'      => 'مرحله نمی‌تواند بیشتر از ۱۰ باشد.',
        ]);

        $registration = TechnicianRegistration::findOrFail($id);

        $previousStep = $registration->current_step;
        $data = ['current_step' => $request->current_step];

        // ست کردن فلگ‌های پیش‌نیاز بر اساس مرحله جدید (جلو بردن)
        // ترتیب جدید: 7=مدارک، 8=قرارداد، 9=ویدیو، 10=سفته
        if ($request->current_step >= 6) {
            $data['status'] = $registration->status === 'rejected' ? 'approved' : $registration->status;
            if ($registration->status === 'pending') {
                $data['status'] = 'approved';
            }
        }
        if ($request->current_step >= 7 && !$registration->documents_uploaded) {
            // مرحله ۷ (مدارک) به بالا = مدارک آپلود شده
            $data['documents_uploaded'] = true;
        }
        if ($request->current_step >= 8 && !$registration->contract_signed_at) {
            // مرحله ۸ (قرارداد) به بالا = قرارداد امضا شده
            $data['contract_signed_at'] = now();
        }
        if ($request->current_step >= 9 && $registration->biometric_status !== 'verified') {
            // مرحله ۹ (ویدیو) = بایومتریک تایید شده
            $data['biometric_status'] = 'verified';
        }
        if ($request->current_step >= 10 && empty($registration->promissory_note_status)) {
            // مرحله ۱۰ (سفته) به بالا = سفته در حالت بررسی
            $data['promissory_note_status'] = 'pending';
        }

        // ریست کردن فلگ‌ها در صورت برگشت به مرحله قبلی
        if ($request->current_step < 10) {
            $data['promissory_note_path'] = null;
            $data['promissory_note_uploaded_at'] = null;
            $data['promissory_note_status'] = null;
            $data['promissory_note_reject_reason'] = null;
        }
        if ($request->current_step < 9) {
            $data['biometric_status'] = null;
            $data['biometric_reject_reason'] = null;
        }
        if ($request->current_step < 8) {
            $data['contract_signed_at'] = null;
            $data['contract_signature'] = null;
        }
        if ($request->current_step < 7) {
            $data['documents_uploaded'] = false;
        }
        if ($request->current_step < 6) {
            $data['status'] = 'pending';
            $data['rejection_reason'] = null;
        }

        $registration->update($data);

        $stepLabels = [
            1 => 'احراز هویت',
            2 => 'اطلاعات شخصی',
            3 => 'اطلاعات تکمیلی',
            4 => 'حوزه فعالیت',
            5 => 'مناطق تحت پوشش',
            6 => 'تکمیل (منتظر بررسی)',
            7 => 'آپلود مدارک',
            8 => 'امضای قرارداد',
            9 => 'احراز هویت ویدیویی',
            10 => 'بارگذاری سفته الکترونیک',
        ];

        $this->logActivity(
            $registration,
            'step_change',
            $previousStep,
            $request->current_step,
            ($stepLabels[$request->current_step] ?? null)
        );

        return redirect()->route('technician.admin.registrations.show', $id)
            ->with('success', 'مرحله به «' . ($stepLabels[$request->current_step] ?? $request->current_step) . '» تغییر یافت.');
    }

    /**
     * بررسی ویدیو احراز هویت (تایید/رد)
     */
    public function registrationBiometricReview(Request $request, $id)
    {
        $this->checkAccess();

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reject_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $registration = TechnicianRegistration::findOrFail($id);

        if ($request->action === 'approve') {
            $registration->update([
                'biometric_status'        => 'verified',
                'biometric_verified_at'   => now(),
                'biometric_reject_reason' => null,
            ]);

            $this->logActivity($registration, 'biometric_review', 'pending', 'verified');

            // ارسال پیامک تایید
            $this->sendStatusSms($registration, 'approved');

            return redirect()->route('technician.admin.registrations.show', $id)
                ->with('success', 'ویدیو احراز هویت تایید شد.');
        }

        // رد ویدیو
        $rejectReason = $request->reject_reason ?: 'ویدیو مورد تایید نیست. لطفاً مجدداً ضبط کنید.';
        $registration->update([
            'biometric_status'        => 'rejected',
            'biometric_reject_reason' => $rejectReason,
            'biometric_verified_at'   => null,
        ]);

        $this->logActivity($registration, 'biometric_review', 'pending', 'rejected', $rejectReason);

        // ارسال پیامک رد
        $this->sendStatusSms($registration, 'rejected');

        return redirect()->route('technician.admin.registrations.show', $id)
            ->with('success', 'ویدیو احراز هویت رد شد.');
    }

    /**
     * بررسی مدارک (تایید/رد با دلیل).
     *
     * رد: documents_uploaded=false می‌شود تا کاربر بتواند دوباره آپلود کند.
     *      فایل‌ها در storage باقی می‌مانند و در re-upload بازنویسی می‌شوند.
     */
    public function registrationDocumentsReview(Request $request, $id)
    {
        $this->checkAccess();

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reject_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:1000'],
        ], [
            'reject_reason.required_if' => 'لطفاً دلیل رد مدارک را وارد کنید.',
        ]);

        $registration = TechnicianRegistration::findOrFail($id);

        if (!$registration->documents_uploaded && $request->action === 'approve') {
            return back()->with('error', 'مدارکی برای تایید وجود ندارد.');
        }

        if ($request->action === 'approve') {
            $registration->update([
                'documents_reject_reason' => null,
            ]);

            $this->logActivity($registration, 'documents_review', null, 'approved');

            return redirect()->route('technician.admin.registrations.show', $id)
                ->with('success', 'مدارک تایید شد.');
        }

        // رد مدارک — کاربر باید دوباره آپلود کند
        $registration->update([
            'documents_uploaded'      => false,
            'documents_reject_reason' => $request->reject_reason,
            // بازگشت به مرحله قبل از مدارک تا resume logic به فاز N برگردد
            'current_step'            => max(6, (int) $registration->current_step - 1),
        ]);

        $this->logActivity($registration, 'documents_review', null, 'rejected', $request->reject_reason);

        $this->sendSmsTemplate($registration, 'sms_documents_rejected_template', [
            'token2' => mb_substr(str_replace(["\n", "\r"], ' ', $request->reject_reason), 0, 25),
        ]);

        return redirect()->route('technician.admin.registrations.show', $id)
            ->with('success', 'مدارک رد شد و پیامک به تکنسین ارسال شد.');
    }

    /**
     * بررسی قرارداد (تایید/رد با دلیل).
     *
     * رد: امضا و تاریخ و شماره قرارداد پاک می‌شوند تا کاربر دوباره امضا کند.
     */
    public function registrationContractReview(Request $request, $id)
    {
        $this->checkAccess();

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reject_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:1000'],
        ], [
            'reject_reason.required_if' => 'لطفاً دلیل رد قرارداد را وارد کنید.',
        ]);

        $registration = TechnicianRegistration::findOrFail($id);

        if (!$registration->contract_signed_at && $request->action === 'approve') {
            return back()->with('error', 'قراردادی برای تایید وجود ندارد.');
        }

        if ($request->action === 'approve') {
            $registration->update([
                'contract_reject_reason' => null,
            ]);

            $this->logActivity($registration, 'contract_review', null, 'approved');

            return redirect()->route('technician.admin.registrations.show', $id)
                ->with('success', 'قرارداد تایید شد.');
        }

        // رد قرارداد — کاربر باید دوباره امضا کند
        $registration->update([
            'contract_signed_at'     => null,
            'contract_signature'     => null,
            'contract_number'        => null,
            'contract_reject_reason' => $request->reject_reason,
            // برگرداندن به مرحله قبل از قرارداد (مدارک، چون در ترتیب جدید قبل از قرارداد است)
            'current_step'           => max(7, (int) $registration->current_step - 1),
        ]);

        $this->logActivity($registration, 'contract_review', null, 'rejected', $request->reject_reason);

        $this->sendSmsTemplate($registration, 'sms_contract_rejected_template', [
            'token2' => mb_substr(str_replace(["\n", "\r"], ' ', $request->reject_reason), 0, 25),
        ]);

        return redirect()->route('technician.admin.registrations.show', $id)
            ->with('success', 'قرارداد رد شد و پیامک به تکنسین ارسال شد.');
    }

    /**
     * ذخیره یادداشت ادمین
     */
    public function registrationUpdateNote(Request $request, $id)
    {
        $this->checkAccess();
        $this->checkEditAccess();

        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $registration = TechnicianRegistration::findOrFail($id);
        $previous = $registration->admin_notes;
        $registration->update(['admin_notes' => $request->admin_notes]);

        $this->logActivity($registration, 'note_change', $previous, $request->admin_notes);

        return response()->json([
            'success' => true,
            'message' => 'یادداشت با موفقیت ذخیره شد.',
        ]);
    }

    /**
     * ذخیره فیلدهای مالی قرارداد (اختصاصی هر تکنسین)
     */
    public function registrationUpdateContractFields(Request $request, $id)
    {
        $this->checkAccess();
        $this->checkEditAccess();

        $request->validate([
            'commission_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'promissory_note_amount'  => ['nullable', 'string', 'max:50'],
        ], [
            'commission_percent.numeric' => 'درصد کارمزد باید عدد باشد.',
            'commission_percent.min'     => 'درصد کارمزد نمی‌تواند منفی باشد.',
            'commission_percent.max'     => 'درصد کارمزد نمی‌تواند بیشتر از ۱۰۰ باشد.',
        ]);

        $registration = TechnicianRegistration::findOrFail($id);
        $previousCommission = $registration->commission_percent;
        $previousNoteAmount = $registration->promissory_note_amount;
        $registration->update([
            'commission_percent'     => $request->commission_percent,
            'promissory_note_amount' => $request->promissory_note_amount,
        ]);

        $this->logActivity(
            $registration,
            'contract_fields_change',
            'commission=' . $previousCommission . ' / promissory=' . $previousNoteAmount,
            'commission=' . $request->commission_percent . ' / promissory=' . $request->promissory_note_amount,
        );

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات مالی قرارداد با موفقیت ذخیره شد.',
        ]);
    }

    /**
     * حذف درخواست ثبت‌نام
     */
    public function registrationDestroy($id)
    {
        if (!auth()->user()->can('delete-technician') && !auth()->user()->can('manage-technicians') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $registration = TechnicianRegistration::findOrFail($id);
        $name = trim($registration->first_name . ' ' . $registration->last_name);
        $registration->delete();

        return redirect()->route('technician.admin.registrations')
            ->with('success', 'درخواست ثبت‌نام' . ($name ? ' «' . $name . '»' : '') . ' با موفقیت حذف شد.');
    }

    /**
     * لیست دسته‌بندی دستگاه‌ها
     */
    public function applianceCategories()
    {
        $this->checkAccess();

        $categories = ApplianceCategory::ordered()->get();

        return view('technician::admin.appliance-categories', compact('categories'));
    }

    /**
     * افزودن دسته‌بندی دستگاه
     */
    public function storeApplianceCategory(Request $request)
    {
        $this->checkAccess();

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:appliance_categories,name'],
        ], [
            'name.required' => 'نام دستگاه الزامی است.',
            'name.unique'   => 'این دستگاه قبلاً اضافه شده است.',
        ]);

        $maxOrder = ApplianceCategory::max('sort_order') ?? 0;

        ApplianceCategory::create([
            'name'       => $request->name,
            'sort_order' => $maxOrder + 1,
            'is_active'  => true,
        ]);

        return redirect()->route('technician.admin.appliance-categories')
            ->with('success', 'دستگاه «' . $request->name . '» با موفقیت اضافه شد.');
    }

    /**
     * ویرایش دسته‌بندی دستگاه
     */
    public function updateApplianceCategory(Request $request, $id)
    {
        $this->checkAccess();

        $category = ApplianceCategory::findOrFail($id);

        $request->validate([
            'name'      => ['required', 'string', 'max:255', 'unique:appliance_categories,name,' . $id],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.required' => 'نام دستگاه الزامی است.',
            'name.unique'   => 'این دستگاه قبلاً اضافه شده است.',
        ]);

        $category->update([
            'name'      => $request->name,
            'is_active' => $request->is_active,
        ]);

        return redirect()->route('technician.admin.appliance-categories')
            ->with('success', 'دستگاه «' . $request->name . '» با موفقیت ویرایش شد.');
    }

    /**
     * حذف دسته‌بندی دستگاه
     */
    public function deleteApplianceCategory($id)
    {
        $this->checkAccess();

        $category = ApplianceCategory::findOrFail($id);
        $name = $category->name;
        $category->delete();

        return redirect()->route('technician.admin.appliance-categories')
            ->with('success', 'دستگاه «' . $name . '» حذف شد.');
    }

    /**
     * ارسال پیامک اطلاع‌رسانی بر اساس وضعیت
     */
    private function sendStatusSms(TechnicianRegistration $registration, string $status): void
    {
        $templateKey = match ($status) {
            'approved' => 'sms_approved_template',
            'rejected' => 'sms_rejected_template',
            default => null,
        };

        if (!$templateKey) {
            return;
        }

        $this->sendSmsTemplate($registration, $templateKey);
    }

    /**
     * بررسی سفته الکترونیک (تایید/رد با دلیل).
     *
     * رد: promissory_note_path پاک می‌شود تا تکنسین دوباره آپلود کند.
     * تایید: promissory_note_status='approved' می‌شود و سپس دکمهٔ
     * «تبدیل به تکنسین فعال» در صفحهٔ ادمین فعال می‌شود.
     */
    public function registrationPromissoryNoteReview(Request $request, $id)
    {
        $this->checkAccess();

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reject_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:1000'],
        ], [
            'reject_reason.required_if' => 'لطفاً دلیل رد سفته را وارد کنید.',
        ]);

        $registration = TechnicianRegistration::findOrFail($id);

        if (empty($registration->promissory_note_path) && $request->action === 'approve') {
            return back()->with('error', 'سفته‌ای برای تایید وجود ندارد.');
        }

        $previousStatus = $registration->promissory_note_status;

        if ($request->action === 'approve') {
            $registration->update([
                'promissory_note_status'        => 'approved',
                'promissory_note_reject_reason' => null,
            ]);

            $this->logActivity($registration, 'promissory_review', $previousStatus, 'approved');

            return redirect()->route('technician.admin.registrations.show', $id)
                ->with('success', 'سفته الکترونیک تایید شد.');
        }

        // رد سفته — تکنسین باید دوباره آپلود کند
        $registration->update([
            'promissory_note_path'          => null,
            'promissory_note_uploaded_at'   => null,
            'promissory_note_status'        => 'rejected',
            'promissory_note_reject_reason' => $request->reject_reason,
        ]);

        $this->logActivity($registration, 'promissory_review', $previousStatus, 'rejected', $request->reject_reason);

        return redirect()->route('technician.admin.registrations.show', $id)
            ->with('success', 'سفته رد شد. تکنسین می‌تواند مجدداً بارگذاری کند.');
    }

    /**
     * تبدیل تکنسین ثبت‌نام‌کرده به تکنسین فعال در ماژول CRM.
     *
     * - فقط زمانی فعال است که promissory_note_status === 'approved'.
     * - اگر در crm_technicians رکوردی با همین موبایل بود، update می‌کند
     *   (بعد از تأیید کاربر در فرم با force=1).
     * - یک User با نقش crm-technician می‌سازد/متصل می‌کند تا تکنسین
     *   بتواند به پنل خود وارد شود.
     */
    public function registrationConvertToActive(Request $request, $id)
    {
        $this->checkAccess();

        $registration = TechnicianRegistration::findOrFail($id);

        if ($registration->promissory_note_status !== 'approved') {
            return back()->with('error', 'برای تبدیل، ابتدا سفتهٔ الکترونیک باید تایید شود.');
        }

        if (empty($registration->mobile)) {
            return back()->with('error', 'موبایل تکنسین خالی است.');
        }

        $existing = CrmTechnician::where('mobile', $registration->mobile)->first();

        // اگر تکراری بود و کاربر force=1 نفرستاد، با پیغام برگرد
        if ($existing && ! $request->boolean('force')) {
            return back()->with('confirm_overwrite', [
                'message' => 'تکنسینی با این موبایل از قبل در سیستم فعال است (' . $existing->full_name . '). آیا اطلاعات با داده‌های ثبت‌نام به‌روزرسانی شود؟',
                'mobile'  => $registration->mobile,
            ]);
        }

        $payload = $this->mapRegistrationToCrmTechnician($registration);

        $result = DB::transaction(function () use ($registration, $existing, $payload) {
            // ۱) User با نقش crm-technician (مثل provisionUser در CRM)
            $user = User::where('mobile', $registration->mobile)->first();
            $generatedPassword = null;

            if (! $user) {
                $generatedPassword = Str::random(10);
                $user = User::create([
                    'name'               => trim(($registration->first_name ?? '') . ' ' . ($registration->last_name ?? '')) ?: $registration->mobile,
                    'first_name'         => $registration->first_name,
                    'mobile'             => $registration->mobile,
                    'password'           => Hash::make($generatedPassword),
                    'is_staff'           => true,
                    'mobile_verified_at' => now(),
                ]);
            }

            if (! $user->hasRole('crm-technician')) {
                $user->assignRole('crm-technician');
            }

            // ۲) تکنسین CRM
            if ($existing) {
                $existing->fill($payload + ['user_id' => $user->id])->save();
                $tech = $existing;
                $action = 'updated';
            } else {
                $tech = CrmTechnician::create($payload + ['user_id' => $user->id]);
                $action = 'created';
            }

            return ['tech' => $tech, 'user' => $user, 'password' => $generatedPassword, 'action' => $action];
        });

        $msg = $result['action'] === 'created'
            ? 'تکنسین با موفقیت در لیست تکنسین‌های فعال ساخته شد.'
            : 'تکنسین موجود با اطلاعات ثبت‌نام به‌روزرسانی شد.';
        $msg .= ' (شناسه CRM: ' . $result['tech']->id . ')';
        if ($result['password']) {
            $msg .= ' — رمز اولیه: ' . $result['password'] . ' (یادداشت کنید؛ دیگر نمایش داده نخواهد شد).';
        }

        $this->logActivity(
            $registration,
            'convert_to_active',
            null,
            'crm_technician_id=' . $result['tech']->id,
            $result['action'] === 'created' ? 'ایجاد تکنسین فعال جدید در CRM' : 'به‌روزرسانی تکنسین موجود در CRM'
        );

        return redirect()->route('technician.admin.registrations.show', $id)->with('success', $msg);
    }

    /** نگاشت فیلدهای TechnicianRegistration به ستون‌های crm_technicians. */
    private function mapRegistrationToCrmTechnician(TechnicianRegistration $r): array
    {
        $fullName = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
        $cities = (array) ($r->tehran_districts ?? $r->tehran_province_cities ?? $r->alborz_cities ?? $r->other_provinces_cities ?? []);
        $address = trim($r->shop_address ?? '') ?: null;

        $specialty = null;
        if (is_array($r->appliance_categories) && count($r->appliance_categories)) {
            // اگر appliance_categories آرایه‌ای از IDها باشد، نام‌ها را resolve می‌کنیم
            $ids = array_filter(array_map('intval', $r->appliance_categories));
            if ($ids) {
                $names = ApplianceCategory::whereIn('id', $ids)->pluck('name')->all();
                if ($names) {
                    $specialty = implode('، ', $names);
                }
            }
            if (! $specialty) {
                $specialty = implode('، ', array_map('strval', $r->appliance_categories));
            }
        }

        return [
            'first_name'      => $r->first_name,
            'firstname_tech'  => $fullName ?: ($r->first_name ?? null),
            'mobile'          => $r->mobile,
            'national_code'   => $r->national_code,
            'phone'           => $r->shop_phone,
            'province'        => $r->province,
            'address'         => $address,
            'specialty'       => $specialty,
            'type_tech'       => $r->activity_type,
            'img_personal'    => $r->doc_photo_3x4 ? Storage::url($r->doc_photo_3x4) : null,
            'cart_img'        => $r->doc_national_card_front ? Storage::url($r->doc_national_card_front) : null,
            'status'          => 'active',
        ];
    }

    /**
     * ثبت یک رخداد در تاریخچهٔ ثبت‌نام تکنسین. تمام تغییرات معنادار
     * ادمین (تأیید/رد، تغییر مرحله، ویرایش، …) از این جا لاگ می‌شوند.
     */
    private function logActivity(
        TechnicianRegistration $registration,
        string $action,
        $from = null,
        $to = null,
        ?string $description = null
    ): void {
        TechnicianRegistrationLog::create([
            'registration_id'    => $registration->id,
            'action'             => $action,
            'from_value'         => $this->stringifyForLog($from),
            'to_value'           => $this->stringifyForLog($to),
            'description'        => $description,
            'changed_by_user_id' => auth()->id(),
            'created_at'         => now(),
        ]);
    }

    /** تبدیل مقدار به string برای ذخیره در ستون text لاگ. */
    private function stringifyForLog($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    /**
     * ارسال SMS قالب‌دار به تکنسین؛ token پیش‌فرض نام کامل تکنسین است،
     * توکن‌های بیشتر (token2/token3) را می‌توان override کرد.
     */
    private function sendSmsTemplate(TechnicianRegistration $registration, string $templateKey, array $extraTokens = []): void
    {
        $template = TechnicianSetting::get($templateKey, '');
        if (empty($template)) {
            return;
        }

        $tokens = array_merge([
            'token' => trim(($registration->first_name ?? '') . ' ' . ($registration->last_name ?? '')),
        ], $extraTokens);

        try {
            app(KavenegarService::class)->sendTemplate($registration->mobile, $template, $tokens);
        } catch (\Exception $e) {
            Log::warning('Failed to send technician SMS', [
                'registration_id' => $registration->id,
                'template'        => $templateKey,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
