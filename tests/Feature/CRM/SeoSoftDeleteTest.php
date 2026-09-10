<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Concerns\ConfirmsSeoDeletion;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Tests\TestCase;

/**
 * محافظتِ حذفِ محتوای سایت:
 *   • soft-delete (حذفِ بازگشت‌پذیر) + بازگردانی،
 *   • ensureForPair روی ترکیبِ حذف‌شده به‌جای INSERTِ تکراری بازمی‌گرداند،
 *   • تأییدِ تایپیِ حذف (اسلاگ باید دقیقاً تایپ شود).
 */
class SeoSoftDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ذخیره/حذفِ DeviceBrandPage کشِ کاتالوگِ اپ را bump می‌کند (settings).
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_device_brand_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('device_id'), $x->unsignedBigInteger('brand_id'),
            $x->boolean('is_active')->default(false), $x->timestamps(), $x->softDeletes(),
            $x->unique(['device_id', 'brand_id'], 'uk_device_brand_page'),
        ]));
    }

    public function test_soft_deleted_brand_is_hidden_and_restorable(): void
    {
        $b = Brand::create(['name' => 'اسنوا', 'slug' => 'snowa']);

        $b->delete();

        // از کوئریِ عادی پنهان است…
        $this->assertNull(Brand::where('slug', 'snowa')->first());
        $this->assertTrue(Brand::onlyTrashed()->where('slug', 'snowa')->exists());

        // …و بازگردانی می‌شود.
        Brand::onlyTrashed()->where('slug', 'snowa')->first()->restore();
        $this->assertNotNull(Brand::where('slug', 'snowa')->first());
    }

    public function test_ensure_for_pair_restores_a_trashed_combo_instead_of_duplicating(): void
    {
        $d = Device::create(['name' => 'جاروبرقی', 'slug' => 'vacuum-cleaner']);
        $b = Brand::create(['name' => 'اسنوا', 'slug' => 'snowa']);

        $page = DeviceBrandPage::ensureForPair($d->id, $b->id);
        $page->forceFill(['is_active' => true])->save();
        $originalId = $page->id;

        $page->delete(); // soft-delete
        $this->assertSame(0, DeviceBrandPage::count());

        // دوباره‌سازی نباید رکوردِ تکراری بسازد (unique می‌شکست) — همان را برمی‌گرداند.
        $again = DeviceBrandPage::ensureForPair($d->id, $b->id);
        $this->assertSame($originalId, $again->id);
        $this->assertFalse($again->trashed());
        $this->assertSame(1, DeviceBrandPage::count());
    }

    public function test_typed_confirmation_rejects_mismatch_and_accepts_exact(): void
    {
        $harness = new class
        {
            use ConfirmsSeoDeletion;

            public function notConfirmed(Request $r, string $expected): bool
            {
                return $this->seoDeletionNotConfirmed($r, $expected);
            }
        };

        // خالی یا نامطابق → تأیید نشده.
        $this->assertTrue($harness->notConfirmed(new Request(['confirm_slug' => '']), 'snowa'));
        $this->assertTrue($harness->notConfirmed(new Request(['confirm_slug' => 'snow']), 'snowa'));

        // دقیق → تأیید شده (not-confirmed = false).
        $this->assertFalse($harness->notConfirmed(new Request(['confirm_slug' => 'snowa']), 'snowa'));

        // ارقامِ فارسی نرمال می‌شوند.
        $this->assertFalse($harness->notConfirmed(new Request(['confirm_slug' => 'tehran-۱۲']), 'tehran-12'));
    }
}
