<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\OrderReview;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\TechnicianSuggestionService;
use Tests\TestCase;

/**
 * امتیازِ تکنسین از نظرسنجیِ مشتری (crm_order_reviews):
 *   • فقط نظرهای «تأییدشده» شمرده می‌شوند.
 *   • کمتر از ۱۰ نظر → امتیازِ پیش‌فرضِ ۲.۵.
 *   • این امتیاز در بُعدِ «رضایت»ِ سیستمِ پخشِ خودکار وزن می‌گیرد.
 */
class TechnicianRatingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->integer('max_order')->nullable();
            $t->integer('max_price')->nullable();
            $t->integer('wallet_balance')->nullable();
            $t->decimal('satisfaction_score', 3, 1)->nullable();
            $t->timestamp('last_assigned_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('crm_order_reviews', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedTinyInteger('rating')->nullable();
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamp('assigned_at')->nullable();
            $t->timestamps();
        });
    }

    private function tech(): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تست', 'mobile' => '0912', 'status' => 'active', 'user_id' => 1,
        ]);
    }

    private function seedReviews(Technician $tech, int $count, int $rating, string $status = OrderReview::STATUS_APPROVED): void
    {
        for ($i = 0; $i < $count; $i++) {
            OrderReview::create([
                'technician_id' => $tech->id, 'rating' => $rating, 'status' => $status,
            ]);
        }
    }

    public function test_default_rating_when_below_threshold(): void
    {
        $this->assertSame(2.5, Technician::effectiveRatingFrom(5.0, 9));
        $this->assertSame(2.5, Technician::effectiveRatingFrom(null, 0));
    }

    public function test_average_used_at_or_above_threshold(): void
    {
        $this->assertSame(4.0, Technician::effectiveRatingFrom(4.0, 10));
        $this->assertSame(3.33, Technician::effectiveRatingFrom(3.333, 25));
    }

    public function test_under_ten_reviews_gets_default_two_and_half(): void
    {
        $tech = $this->tech();
        $this->seedReviews($tech, 9, 5); // ۹ نظرِ ۵ ستاره ولی زیرِ حدِ نصاب

        $this->assertSame(2.5, $tech->effectiveRating());
        $this->assertSame(9, $tech->reviewRatingStats()['count']);
    }

    public function test_ten_or_more_reviews_uses_real_average(): void
    {
        $tech = $this->tech();
        $this->seedReviews($tech, 8, 5);
        $this->seedReviews($tech, 4, 4); // مجموع ۱۲ نظر، میانگین = (8*5+4*4)/12 = 4.6667

        $stats = $tech->reviewRatingStats();
        $this->assertSame(12, $stats['count']);
        $this->assertSame(4.67, $tech->effectiveRating());
    }

    public function test_only_approved_reviews_count(): void
    {
        $tech = $this->tech();
        $this->seedReviews($tech, 12, 5, OrderReview::STATUS_APPROVED);
        $this->seedReviews($tech, 5, 1, OrderReview::STATUS_PENDING);   // نباید بشمارد
        $this->seedReviews($tech, 5, 1, OrderReview::STATUS_REJECTED);  // نباید بشمارد

        $this->assertSame(12, $tech->reviewRatingStats()['count']);
        $this->assertSame(5.0, $tech->effectiveRating());
    }

    public function test_scoring_satisfaction_reflects_reviews(): void
    {
        $svc = app(TechnicianSuggestionService::class);

        $high = $this->tech();
        $this->seedReviews($high, 10, 5); // میانگین ۵ → رضایتِ کامل

        $low = Technician::forceCreate(['first_name' => 'کم', 'mobile' => '0913', 'status' => 'active', 'user_id' => 2]);
        $this->seedReviews($low, 3, 5); // زیرِ ۱۰ → پیش‌فرضِ ۲.۵

        $scores = $svc->scoreAll(collect([$high, $low]), []);

        // وزنِ رضایت ۲۰ است: ۵/۵*۲۰=۲۰ برای high، ۲.۵/۵*۲۰=۱۰ برای low.
        $this->assertSame(20, $scores[$high->id]->breakdown['satisfaction']);
        $this->assertSame(10, $scores[$low->id]->breakdown['satisfaction']);
    }
}
