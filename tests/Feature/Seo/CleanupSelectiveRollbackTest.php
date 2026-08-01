<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * برگرداندنِ انتخابی.
 *
 * تشخیصِ آلودگی چندنشانه‌ای است و ناگزیر گاهی متنِ دستیِ خوبی را هم می‌گیرد —
 * مثلاً عنوانی که فقط یک نویسه از سقف رد کرده. برگرداندنِ «همه یا هیچ» آن‌وقت
 * بی‌فایده است: یا متایِ وردپرسی هم برمی‌گردد یا متنِ دستی از دست می‌رود.
 */
class CleanupSelectiveRollbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_brands', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('meta_title', 200)->nullable();
            $t->string('meta_description', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('meta_title', 200)->nullable();
            $t->string('meta_description', 500)->nullable();
            $t->timestamps();
        });

        DB::table('crm_brands')->insert([
            [
                'id' => 1, 'name' => 'بوش', 'slug' => 'bosch',
                'meta_title' => 'نمایندگی بوش bosch در ایران | مرکز تخصصی تعمیرات بوش – تعمیرآنلاین',
                'meta_description' => null,
            ],
            [
                'id' => 2, 'name' => 'زانوسی', 'slug' => 'zanussi',
                // متنِ دستیِ خوب که فقط چند نویسه از سقف رد کرده.
                'meta_title' => 'تعمیر تخصصی لوازم خانگی زانوسی در محل با ضمانت ۱۸۰ روزه و اعزام فوری',
                'meta_description' => null,
            ],
        ]);
    }

    private function titles(): array
    {
        return DB::table('crm_brands')->orderBy('id')->pluck('meta_title', 'id')->all();
    }

    public function test_restoring_one_backup_leaves_the_others_cleared(): void
    {
        Artisan::call('seo:meta:cleanup', ['--apply' => true, '--table' => 'brands']);

        $this->assertSame([1 => null, 2 => null], $this->titles(), 'هر دو باید خالی شده باشند.');

        // شناسهٔ پشتیبانِ همان برندی که متنش دستی بود.
        $backupId = DB::table('seo_meta_import_backups')->where('source_id', 2)->value('id');

        Artisan::call('seo:meta:cleanup', ['--rollback' => true, '--id' => [$backupId]]);

        $titles = $this->titles();
        $this->assertNull($titles[1], 'متایِ وردپرسی نباید برگشته باشد.');
        $this->assertSame(
            'تعمیر تخصصی لوازم خانگی زانوسی در محل با ضمانت ۱۸۰ روزه و اعزام فوری',
            $titles[2],
            'متنِ دستی عیناً برنگشت.'
        );
    }

    public function test_a_restored_backup_is_not_restored_twice(): void
    {
        Artisan::call('seo:meta:cleanup', ['--apply' => true, '--table' => 'brands']);
        $backupId = DB::table('seo_meta_import_backups')->where('source_id', 2)->value('id');

        Artisan::call('seo:meta:cleanup', ['--rollback' => true, '--id' => [$backupId]]);
        DB::table('crm_brands')->where('id', 2)->update(['meta_title' => 'عنوانِ تازه‌ای که بعداً نوشتم']);

        Artisan::call('seo:meta:cleanup', ['--rollback' => true, '--id' => [$backupId]]);

        $this->assertSame(
            'عنوانِ تازه‌ای که بعداً نوشتم',
            $this->titles()[2],
            'برگردانِ دوباره نباید روی نوشتهٔ جدید بنویسد.'
        );
    }

    public function test_rollback_without_ids_still_restores_everything(): void
    {
        Artisan::call('seo:meta:cleanup', ['--apply' => true, '--table' => 'brands']);
        Artisan::call('seo:meta:cleanup', ['--rollback' => true]);

        foreach ($this->titles() as $title) {
            $this->assertNotNull($title);
        }
    }

    public function test_the_backup_listing_shows_ids_and_names(): void
    {
        Artisan::call('seo:meta:cleanup', ['--apply' => true, '--table' => 'brands']);
        Artisan::call('seo:meta:cleanup', ['--list-backup' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString('بوش', $output);
        $this->assertStringContainsString('زانوسی', $output);
        $this->assertStringContainsString('--rollback --id=', $output);
    }
}
