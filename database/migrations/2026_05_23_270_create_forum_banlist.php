<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لیست بن انجمن — IP یا ایمیل که از submit سوال/پاسخ ممنوع شده‌اند.
 *
 * در POST کاربر چک می‌شود؛ اگر مطابقت داشت 403 برمی‌گردد.
 * expires_at = null یعنی دائمی.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_forum_banlist')) {
            return;
        }

        Schema::create('site_forum_banlist', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);              // 'ip' | 'email'
            $table->string('value', 250);
            $table->string('reason', 250)->nullable();
            $table->unsignedBigInteger('banned_by_user_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index(['type', 'expires_at']);

            $table->foreign('banned_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_forum_banlist');
    }
};
