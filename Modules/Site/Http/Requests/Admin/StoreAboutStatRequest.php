<?php

namespace Modules\Site\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Site\Models\AboutStat;

class StoreAboutStatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->can('manage-site-settings') || $u->can('manage-site') || $u->can('manage-permissions'));
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'key'          => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('site_about_stats', 'key')->ignore($id)],
            'value'        => ['required', 'string', 'max:60'],
            'label'        => ['required', 'string', 'max:120'],
            'tone'         => ['required', Rule::in(AboutStat::TONES)],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'کلید الزامی است.',
            'key.regex'    => 'کلید فقط حروف کوچک انگلیسی، عدد و _ — با حرف شروع شود.',
            'key.unique'   => 'این کلید قبلاً ثبت شده است.',
            'value.required' => 'مقدار آمار الزامی است.',
            'label.required' => 'برچسب فارسی الزامی است.',
            'tone.in'      => 'تم انتخاب‌شده معتبر نیست.',
        ];
    }
}
