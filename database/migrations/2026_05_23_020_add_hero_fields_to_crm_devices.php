<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_devices', 'subtitle')) {
                $table->string('subtitle', 200)->nullable()->after('description');
            }
            if (! Schema::hasColumn('crm_devices', 'eyebrow')) {
                $table->string('eyebrow', 120)->nullable()->after('subtitle');
            }
            if (! Schema::hasColumn('crm_devices', 'service_steps')) {
                $table->json('service_steps')->nullable()->after('support_info');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $cols = array_filter(
                ['subtitle', 'eyebrow', 'service_steps'],
                fn ($c) => Schema::hasColumn('crm_devices', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
