<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('mobile', 11)->unique();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('national_code', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('business_name')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_staff')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('mobile');
            $table->index('is_staff');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('mobile', 11)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
