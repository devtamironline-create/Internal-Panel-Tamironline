<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
        });

        // Insert default settings
        DB::table('settings')->insert([
            ['key' => 'site_name', 'value' => 'اتوماسیون اداری'],
            ['key' => 'site_subtitle', 'value' => 'تعمیرآنلاین'],
            ['key' => 'logo', 'value' => null],
            ['key' => 'favicon', 'value' => null],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
