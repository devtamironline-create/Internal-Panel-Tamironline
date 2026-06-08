<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->text('warranty_text')->nullable()->after('meta_description');
            $table->text('support_info')->nullable()->after('warranty_text');
        });

        Schema::table('crm_brands', function (Blueprint $table) {
            $table->text('warranty_text')->nullable()->after('meta_description');
            $table->text('support_info')->nullable()->after('warranty_text');
            // device slugs that this brand supports (e.g. ["washing-machine", "dishwasher"])
            $table->json('supported_device_slugs')->nullable()->after('support_info');
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            $table->dropColumn(['warranty_text', 'support_info']);
        });

        Schema::table('crm_brands', function (Blueprint $table) {
            $table->dropColumn(['warranty_text', 'support_info', 'supported_device_slugs']);
        });
    }
};
