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
                'hero_title'         => TechnicianSetting::get('hero_title',          $defaults['hero_title']),
                'hero_subtitle'      => TechnicianSetting::get('hero_subtitle',       $defaults['hero_subtitle']),
                'hero_description'   => TechnicianSetting::get('hero_description',    $defaults['hero_description']),
                'hero_cta_text'      => TechnicianSetting::get('hero_cta_text',       $defaults['hero_cta_text']),
                'hero_badge'         => TechnicianSetting::get('hero_badge',          $defaults['hero_badge']),
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
                'cta_button_text'    => TechnicianSetting::get('cta_button_text',     $defaults['cta_button_text']),
            ];
        } catch (\Exception $e) {
            // اگه جدول هنوز migrate نشده یا DB در دسترس نیست، از defaults استفاده کن
            $data = [
                'page_title'         => $defaults['page_title'],
                'brand_name'         => $defaults['brand_name'],
                'hero_title'         => $defaults['hero_title'],
                'hero_subtitle'      => $defaults['hero_subtitle'],
                'hero_description'   => $defaults['hero_description'],
                'hero_cta_text'      => $defaults['hero_cta_text'],
                'hero_badge'         => $defaults['hero_badge'],
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
                'cta_button_text'    => $defaults['cta_button_text'],
            ];
        }

        return view('technician::landing', $data);
    }
}
