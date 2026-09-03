<?php

namespace Tests\Feature\CustomerApp;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * مهاجرتِ تأییدِ خودکارِ نظرهای موجود: فقط pendingها approved می‌شوند و
 * rejectedها دست‌نخورده می‌مانند (بی‌خطر برای production).
 */
class ApproveExistingReviewsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_order_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status')->default('pending');
            $t->timestamp('moderated_at')->nullable();
            $t->timestamps();
        });
    }

    public function test_pending_reviews_become_approved_and_rejected_stay(): void
    {
        $pending = DB::table('crm_order_reviews')->insertGetId(['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $rejected = DB::table('crm_order_reviews')->insertGetId(['status' => 'rejected', 'created_at' => now(), 'updated_at' => now()]);
        $already = DB::table('crm_order_reviews')->insertGetId(['status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);

        $migration = require base_path('database/migrations/2026_09_03_130000_approve_existing_order_reviews.php');
        $migration->up();

        $this->assertSame('approved', DB::table('crm_order_reviews')->where('id', $pending)->value('status'));
        $this->assertNotNull(DB::table('crm_order_reviews')->where('id', $pending)->value('moderated_at'));
        // ردشده دست‌نخورده
        $this->assertSame('rejected', DB::table('crm_order_reviews')->where('id', $rejected)->value('status'));
        $this->assertSame('approved', DB::table('crm_order_reviews')->where('id', $already)->value('status'));
    }
}
