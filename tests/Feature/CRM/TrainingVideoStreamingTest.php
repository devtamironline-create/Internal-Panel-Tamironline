<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\TrainingVideo;
use Tests\TestCase;

/**
 * استریم ویدیوی آموزشی با HTTP Range — لازمهٔ seek و «ادامه از همان‌جا»
 * روی نتِ ضعیف. بدون 206، هر قطعی یعنی دانلود کل فایل از اول.
 */
class TrainingVideoStreamingTest extends TestCase
{
    private const BODY = '0123456789ABCDEFGHIJ'; // ۲۰ بایت — بازه‌ها قابل پیش‌بینی

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_training_videos', function ($t) {
            $t->id();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('title');
            $t->text('description')->nullable();
            $t->boolean('is_local')->default(false);
            $t->string('video_url', 500)->nullable();
            $t->string('video_low_url', 500)->nullable();
            $t->string('thumbnail', 500)->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // توکن‌های Sanctum — مسیر احراز APK (پروکسی هم‌مبدأ اپ).
        Schema::create('personal_access_tokens', function ($t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Storage::fake('public');
        Storage::disk('public')->put('crm/training/videos/a.mp4', self::BODY);
        Storage::disk('public')->put('crm/training/videos/a-low.mp4', 'LOWQ');
    }

    private function video(array $extra = []): TrainingVideo
    {
        return TrainingVideo::forceCreate(array_merge([
            'title' => 'آموزش تست',
            'is_local' => true,
            'video_url' => 'crm/training/videos/a.mp4',
            'is_active' => true,
        ], $extra));
    }

    private function asTech(): Technician
    {
        $tech = Technician::forceCreate(['first_name' => 'تست', 'mobile' => '09120000000', 'status' => 'active']);
        $this->actingAs($tech, 'tech');

        return $tech;
    }

    public function test_a_plain_request_gets_the_whole_file_with_accept_ranges(): void
    {
        $this->asTech();
        $response = $this->get('/crm/training/'.$this->video()->id.'/video');

        $response->assertOk();
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
        $this->assertSame((string) strlen(self::BODY), $response->headers->get('Content-Length'));
        $this->assertSame(self::BODY, $response->streamedContent());
    }

    public function test_a_byte_range_returns_206_with_exactly_those_bytes(): void
    {
        $this->asTech();
        $response = $this->get('/crm/training/'.$this->video()->id.'/video', ['Range' => 'bytes=0-4']);

        $response->assertStatus(206);
        $this->assertSame('bytes 0-4/20', $response->headers->get('Content-Range'));
        $this->assertSame('5', $response->headers->get('Content-Length'));
        $this->assertSame('01234', $response->streamedContent());
    }

    public function test_an_open_ended_range_resumes_to_the_end(): void
    {
        // دقیقاً سناریوی نتِ ضعیف: قطع شد، مرورگر از بایتِ ۱۵ ادامه می‌خواهد.
        $this->asTech();
        $response = $this->get('/crm/training/'.$this->video()->id.'/video', ['Range' => 'bytes=15-']);

        $response->assertStatus(206);
        $this->assertSame('bytes 15-19/20', $response->headers->get('Content-Range'));
        $this->assertSame('FGHIJ', $response->streamedContent());
    }

    public function test_an_out_of_bounds_range_gets_416(): void
    {
        $this->asTech();
        $response = $this->get('/crm/training/'.$this->video()->id.'/video', ['Range' => 'bytes=999-']);

        $response->assertStatus(416);
    }

    public function test_the_low_quality_variant_is_served_with_q_low(): void
    {
        $this->asTech();
        $video = $this->video(['video_low_url' => 'crm/training/videos/a-low.mp4']);

        $response = $this->get('/crm/training/'.$video->id.'/video?q=low');

        $response->assertOk();
        $this->assertSame('LOWQ', $response->streamedContent());
        $this->assertNotNull($video->lowPlaybackUrl(), 'وقتی نسخهٔ کم‌حجم هست باید URL بدهد.');
    }

    public function test_without_a_low_variant_q_low_falls_back_to_the_main_file(): void
    {
        $this->asTech();
        $video = $this->video();

        $response = $this->get('/crm/training/'.$video->id.'/video?q=low');

        $response->assertOk();
        $this->assertSame(self::BODY, $response->streamedContent());
        $this->assertNull($video->lowPlaybackUrl());
    }

    public function test_guests_cannot_stream_training_files(): void
    {
        $this->get('/crm/training/'.$this->video()->id.'/video')->assertForbidden();
    }

    /**
     * مسیر APK: WebView کوکی شخص‌ثالث ندارد؛ پروکسیِ اپ همان توکن Sanctum
     * مسیرهای /v1/technician/* را Bearer می‌فرستد — باید پذیرفته شود.
     */
    public function test_a_sanctum_bearer_token_streams_without_any_cookie(): void
    {
        $tech = Technician::forceCreate(['first_name' => 'تست', 'mobile' => '09120000000', 'status' => 'active']);
        $token = $tech->createToken('apk')->plainTextToken;
        $video = $this->video(['video_low_url' => 'crm/training/videos/a-low.mp4']);

        $response = $this->get('/crm/training/'.$video->id.'/video', ['Authorization' => 'Bearer '.$token]);
        $response->assertOk();
        $this->assertSame(self::BODY, $response->streamedContent());

        // Range و q=low هم با همین احراز کار می‌کنند.
        $partial = $this->get('/crm/training/'.$video->id.'/video?q=low', [
            'Authorization' => 'Bearer '.$token,
            'Range' => 'bytes=0-1',
        ]);
        $partial->assertStatus(206);
        $this->assertSame('LO', $partial->streamedContent());
    }

    public function test_an_invalid_bearer_token_is_rejected(): void
    {
        $this->get('/crm/training/'.$this->video()->id.'/video', ['Authorization' => 'Bearer invalid-token'])
            ->assertForbidden();
    }
}
