<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sync_logs', function (Blueprint $table) {
            $table->id();

            // inbound  = WP→Laravel  /  outbound = Laravel→WP
            $table->enum('direction', ['inbound', 'outbound'])->index();

            // order | technician | customer | wallet_tx | invoice | taxonomy | setting | other
            $table->string('entity_type', 32)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedBigInteger('wp_id')->nullable()->index();

            // برای outbound: نام endpoint WP (order-update, financial-upsert, ...)
            // برای inbound:  مسیر روت لاراولی (api/crm/sync/order, ...)
            $table->string('endpoint', 128)->nullable();

            // success | failed | skipped (مثلاً به دلیل تنظیم سینک)
            $table->enum('status', ['success', 'failed', 'skipped'])->index();

            $table->integer('http_status')->nullable();
            $table->string('error_message', 500)->nullable();

            $table->json('payload')->nullable();
            $table->json('response')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sync_logs');
    }
};
