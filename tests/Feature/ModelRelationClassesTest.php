<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Tests\TestCase;

/**
 * رابطه‌های مدل باید به کلاسِ موجود اشاره کنند.
 *
 * چرا این تست وجود دارد: `Conversation::class` داخل namespaceِ
 * `App\Models` به `App\Models\Conversation` حل می‌شود، در حالی که کلاس در
 * `App\Models\Chat\` است. بدونِ `use`، این خطا در زمانِ کامپایل دیده
 * نمی‌شود و فقط وقتی معلوم می‌شود که کاربر روی صفحه‌ای برود که رابطه را
 * صدا می‌زند — یعنی ۵۰۰ روی پروداکشن.
 *
 * `getRelated()` فقط کلاسِ مقصد را می‌سازد و به دیتابیس نمی‌زند، پس این
 * تست به هیچ جدولی نیاز ندارد.
 */
class ModelRelationClassesTest extends TestCase
{
    public function test_the_announcement_relations_point_at_classes_that_exist(): void
    {
        $announcement = new Announcement;

        $this->assertInstanceOf(Conversation::class, $announcement->conversation()->getRelated());
        $this->assertInstanceOf(Message::class, $announcement->message()->getRelated());
    }

    /**
     * جاروی سراسری: هر `X::class` کوتاه در `app/` و `Modules/` باید قابلِ
     * بارگذاری باشد.
     *
     * ارجاع‌های کاملاً مقیدشده (`\Modules\...`) و متنِ داخلِ کامنت کنار
     * گذاشته می‌شوند؛ آن‌ها هشدارِ کاذب می‌سازند.
     */
    public function test_no_short_class_reference_is_unresolvable(): void
    {
        $broken = [];

        foreach (['app', 'Modules'] as $dir) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                // مهاجرت‌ها کلاسِ ناشناس دارند و ویوها PHP معمولی نیستند.
                if (str_contains($path, '/Database/Migrations/') || str_contains($path, '/Resources/views/')) {
                    continue;
                }

                $source = file_get_contents($path);
                if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
                    continue;
                }

                // کامنت‌ها حذف می‌شوند تا `Command::class` داخلِ docblock
                // به‌عنوان ارجاعِ واقعی شمرده نشود.
                $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);

                $imports = [];
                preg_match_all('/^use\s+([^;\s(]+)(?:\s+as\s+(\w+))?;/m', $code, $uses, PREG_SET_ORDER);
                foreach ($uses as $use) {
                    $alias = $use[2] ?? substr(strrchr('\\'.$use[1], '\\'), 1);
                    $imports[$alias] = ltrim($use[1], '\\');
                }

                preg_match_all('/(?<![\\\\\w])([A-Z]\w+)::class\b/', $code, $matches);

                foreach (array_unique($matches[1] ?? []) as $short) {
                    $fqcn = $imports[$short] ?? ($namespace[1].'\\'.$short);

                    if (class_exists($fqcn) || interface_exists($fqcn) || enum_exists($fqcn) || trait_exists($fqcn)) {
                        continue;
                    }

                    $broken[] = str_replace(base_path().'/', '', $path).' → '.$fqcn;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($broken)),
            "ارجاع به کلاسی که وجود ندارد — روی پروداکشن ۵۰۰ می‌دهد:\n".implode("\n", array_unique($broken)));
    }
}
