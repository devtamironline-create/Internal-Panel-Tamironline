<?php

namespace Modules\Technician\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'hero_badge'         => TechnicianSetting::get('hero_badge',          $defaults['hero_badge']),
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
            'cta_button_text'    => TechnicianSetting::get('cta_button_text',     $defaults['cta_button_text']),
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
            'hero_title', 'hero_subtitle', 'hero_description', 'hero_cta_text', 'hero_badge',
            'benefits_title', 'steps_title', 'requirements_title', 'faq_title',
            'cta_title', 'cta_description', 'cta_button_text',
        ];

        foreach ($simpleFields as $field) {
            if ($request->has($field)) {
                TechnicianSetting::set($field, $request->input($field));
            }
        }

        // آپلود لوگوی صفحه عمومی
        if ($request->hasFile('brand_logo')) {
            $request->validate(['brand_logo' => 'image|max:2048']);
            $old = TechnicianSetting::get('brand_logo');
            if ($old) Storage::disk('public')->delete($old);
            $path = $request->file('brand_logo')->store('technician', 'public');
            TechnicianSetting::set('brand_logo', $path);
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
}
