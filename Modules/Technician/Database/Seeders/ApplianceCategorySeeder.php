<?php

namespace Modules\Technician\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Technician\Models\ApplianceCategory;

class ApplianceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'ماشین لباسشویی',
            'ماشین ظرفشویی',
            'ساید بای ساید',
            'یخچال و فریزر',
            'اسپیلت',
            'آبگرمکن',
            'پکیج',
            'فر و اجاق گاز',
            'مایکروویو و سولاردام',
            'هود',
            'تصفیه آب',
            'جاروبرقی',
            'جارو شارژی',
            'آبسردکن',
            'تصفیه هوا',
            'لوازم آشپزخانه‌ای کوچک',
            'صوتی و تصویری',
            'مانیتور',
            'نصب آنتن',
            'جارو رباتیک',
            'خشک‌کن',
        ];

        foreach ($categories as $index => $name) {
            ApplianceCategory::firstOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
