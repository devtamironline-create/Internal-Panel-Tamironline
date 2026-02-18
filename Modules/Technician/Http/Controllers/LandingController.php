<?php

namespace Modules\Technician\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Technician\Models\TechnicianSetting;

class LandingController extends Controller
{
    public function show()
    {
        $defaults = TechnicianSetting::defaults();

        try {
            $data = [
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
                'benefits'           => TechnicianSetting::getJson('benefits',         json_decode($defaults['benefits'], true)),
                'steps_title'        => TechnicianSetting::get('steps_title',         $defaults['steps_title']),
                'steps'              => TechnicianSetting::getJson('steps',            json_decode($defaults['steps'], true)),
                'requirements_title' => TechnicianSetting::get('requirements_title',  $defaults['requirements_title']),
                'requirements'       => TechnicianSetting::getJson('requirements',     json_decode($defaults['requirements'], true)),
                'faq_title'          => TechnicianSetting::get('faq_title',           $defaults['faq_title']),
                'faq'                => TechnicianSetting::getJson('faq',              json_decode($defaults['faq'], true)),
                'cta_title'          => TechnicianSetting::get('cta_title',           $defaults['cta_title']),
                'cta_description'    => TechnicianSetting::get('cta_description',     $defaults['cta_description']),
                'cta_badge'          => TechnicianSetting::get('cta_badge',           $defaults['cta_badge']),
                'cta_button_text'    => TechnicianSetting::get('cta_button_text',     $defaults['cta_button_text']),
                'cta_button_link'    => TechnicianSetting::get('cta_button_link',     $defaults['cta_button_link']),
                'cta_phone_text'     => TechnicianSetting::get('cta_phone_text',      $defaults['cta_phone_text']),
                'cta_phone'          => TechnicianSetting::get('cta_phone',           $defaults['cta_phone']),
                'cta_footnote'       => TechnicianSetting::get('cta_footnote',        $defaults['cta_footnote']),
            ];
        } catch (\Exception $e) {
            // اگه جدول هنوز migrate نشده یا DB در دسترس نیست، از defaults استفاده کن
            $data = [
                'page_title'         => $defaults['page_title'],
                'brand_name'         => $defaults['brand_name'],
                'brand_logo'         => null,
                'hero_title'         => $defaults['hero_title'],
                'hero_subtitle'      => $defaults['hero_subtitle'],
                'hero_description'   => $defaults['hero_description'],
                'hero_cta_text'      => $defaults['hero_cta_text'],
                'hero_cta_link'      => $defaults['hero_cta_link'],
                'hero_secondary_text' => $defaults['hero_secondary_text'],
                'hero_secondary_link' => $defaults['hero_secondary_link'],
                'hero_badge'         => $defaults['hero_badge'],
                'hero_bg_image'      => null,
                'hero_overlay_color' => '#0f2a4a',
                'hero_overlay_opacity' => '60',
                'benefits_title'     => $defaults['benefits_title'],
                'benefits'           => json_decode($defaults['benefits'], true),
                'steps_title'        => $defaults['steps_title'],
                'steps'              => json_decode($defaults['steps'], true),
                'requirements_title' => $defaults['requirements_title'],
                'requirements'       => json_decode($defaults['requirements'], true),
                'faq_title'          => $defaults['faq_title'],
                'faq'                => json_decode($defaults['faq'], true),
                'cta_title'          => $defaults['cta_title'],
                'cta_description'    => $defaults['cta_description'],
                'cta_badge'          => $defaults['cta_badge'],
                'cta_button_text'    => $defaults['cta_button_text'],
                'cta_button_link'    => $defaults['cta_button_link'],
                'cta_phone_text'     => $defaults['cta_phone_text'],
                'cta_phone'          => $defaults['cta_phone'],
                'cta_footnote'       => $defaults['cta_footnote'],
            ];
        }

        return view('technician::landing', $data);
    }
}
