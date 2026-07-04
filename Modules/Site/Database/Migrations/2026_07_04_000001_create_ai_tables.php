<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زیرساختِ AI پنل — رجیستریِ مدل‌ها + لاگِ تصمیم‌ها.
 *
 * ai_models: هر ردیف یک اتصال به یک مدل (endpoint سازگار با OpenAI، مثلِ
 *   gatewayِ آروان‌کلاد). api_key رمزنگاری‌شده ذخیره می‌شود (cast در مدل).
 * ai_decision_logs: هر تصمیم/پیشنهادِ AI (مثلاً مودریشنِ یک کامنت) برای
 *   حسابرسی و شفافیت — مخصوصاً وقتی بعداً اختیاراتِ بیشتری به AI داده شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_models')) {
            Schema::create('ai_models', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);                 // نامِ نمایشی
                $table->string('slug', 100)->unique();        // شناسهٔ یکتا
                $table->string('endpoint', 500);              // base URL (…/v1)
                $table->text('api_key')->nullable();          // رمزنگاری‌شده (cast)
                $table->string('model_name', 150);            // پارامترِ model در API
                $table->unsignedInteger('max_tokens')->default(1000);
                $table->decimal('temperature', 3, 2)->default(0.20);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['is_active', 'is_default']);
            });
        }

        if (! Schema::hasTable('ai_decision_logs')) {
            Schema::create('ai_decision_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
                $table->string('task', 60)->default('moderation');   // نوعِ وظیفه
                $table->nullableMorphs('subject');                    // کامنت/سوالِ انجمن
                $table->string('decision', 30)->nullable();           // approve/reject/spam/none
                $table->decimal('confidence', 4, 3)->nullable();      // 0..1
                $table->text('reason')->nullable();
                $table->boolean('applied')->default(false);           // اعمال شد؟ (auto/hybrid)
                $table->unsignedInteger('prompt_tokens')->nullable();
                $table->unsignedInteger('completion_tokens')->nullable();
                $table->json('raw_response')->nullable();
                $table->foreignId('created_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['task', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_decision_logs');
        Schema::dropIfExists('ai_models');
    }
};
