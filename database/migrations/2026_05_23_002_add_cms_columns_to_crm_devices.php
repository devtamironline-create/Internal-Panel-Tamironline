<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون‌های CMS-override به crm_devices.
 * نکته: ستون `tone` فعلی (مثل tone-blue) برای کلاس CSS است؛ ستون جدید
 * `accent` برای کد hex (مثل #3B82F6) که در صفحه‌ی detail دستگاه استفاده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->string('short_name', 80)->nullable()->after('name');
            $table->text('description')->nullable()->after('thumbnail');
            $table->string('service_name', 160)->nullable()->after('description');
            $table->string('technician_name', 160)->nullable()->after('service_name');
            $table->unsignedInteger('starting_price')->nullable()->after('technician_name'); // ریال
            $table->string('accent', 20)->nullable()->after('starting_price'); // hex
            $table->string('bg', 20)->nullable()->after('accent');             // hex
            $table->json('issues')->nullable()->after('bg');                   // [{title, description}]
            $table->json('faq')->nullable()->after('issues');                  // [{question, answer}]
            $table->string('meta_title', 200)->nullable()->after('faq');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->dropColumn([
                'short_name', 'description', 'service_name', 'technician_name',
                'starting_price', 'accent', 'bg',
                'issues', 'faq',
                'meta_title', 'meta_description',
            ]);
        });
    }
};
