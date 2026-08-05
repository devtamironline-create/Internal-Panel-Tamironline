<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Schema;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\MetaResolver;
use Modules\Seo\Support\MetaLength;
use Modules\Site\Models\Forum\Question;
use Tests\TestCase;

/**
 * دیسکریپشنِ سؤال‌های فروم باید از متنِ خودِ سؤال ساخته شود.
 *
 * چرا این تست وجود دارد: قالبِ فروم `%excerpt%` بود ولی `excerpt_attr`
 * در رجیستری null، پس excerpt برای همه خالی رندر می‌شد و MetaResolver
 * بی‌سروصدا `site_description` ثابت را جایش می‌گذاشت — ۸۹ صفحهٔ فروم در
 * SEMrush با یک دیسکریپشنِ بایت‌به‌بایت یکسان.
 */
class ForumMetaUniquenessTest extends TestCase
{
    private const SITE_DESCRIPTION = 'اولین اپلیکیشن تخصص تعمیرات با 180 روز ضمانت رسمی';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general');
            $t->timestamps();
        });

        Schema::create('seo_meta', function ($t) {
            $t->id();
            $t->string('seoable_type');
            $t->unsignedBigInteger('seoable_id');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('site_forum_questions', function ($t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('status', 20)->default('approved');
            $t->unsignedInteger('view_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        SeoSetting::query()->create(['key' => 'site_description', 'value' => self::SITE_DESCRIPTION]);
    }

    private function question(string $slug, string $title, ?string $body): Question
    {
        return Question::forceCreate([
            'slug' => $slug,
            'title' => $title,
            'body' => $body,
            'status' => 'approved',
            'published_at' => now(),
        ]);
    }

    private function describe(Question $question): string
    {
        return app(MetaResolver::class)
            ->titleAndDescription('forum_question', $question)['description'];
    }

    public function test_two_different_questions_get_two_different_descriptions(): void
    {
        // همان دو نمونهٔ واقعیِ گزارشِ SEMrush.
        $first = $this->question(
            '12-gas-burner',
            'یکی از شعله‌های اجاق گازم توش گاز نمیاد علتش چیه',
            'سلام. اجاق گاز پنج شعلهٔ اسنوا دارم و یکی از شعله‌ها اصلاً گاز نمی‌دهد. بقیه سالم‌اند. فندک هم جرقه می‌زند ولی شعله روشن نمی‌شود.'
        );
        $second = $this->question(
            '100-washer-mold',
            'برای از بین بردن کپک و سیاهی لاستیک دور درب لباسشویی',
            'دور لاستیک درب لباسشویی سیاه شده و بوی نم می‌دهد. با چه ماده‌ای می‌شود تمیزش کرد که به لاستیک آسیب نزند؟'
        );

        $one = $this->describe($first);
        $two = $this->describe($second);

        $this->assertNotSame($one, $two, 'دو سؤالِ متفاوت هنوز دیسکریپشنِ یکسان می‌گیرند.');
        $this->assertNotSame(self::SITE_DESCRIPTION, $one);
        $this->assertNotSame(self::SITE_DESCRIPTION, $two);

        // و واقعاً از متنِ خودِ سؤال آمده باشد، نه از جای دیگر.
        $this->assertStringContainsString('اجاق گاز پنج شعله', $one);
        $this->assertStringContainsString('لاستیک درب لباسشویی', $two);
    }

    public function test_the_description_respects_the_length_budget(): void
    {
        $question = $this->question(
            'long-body',
            'سؤال با متن طولانی',
            str_repeat('ماشین لباسشویی آب را تخلیه نمی‌کند و ارور می‌دهد. ', 30)
        );

        $this->assertLessThanOrEqual(MetaLength::DESCRIPTION_MAX, mb_strlen($this->describe($question)));
    }

    public function test_html_in_the_body_never_reaches_the_description(): void
    {
        $question = $this->question(
            'html-body',
            'سؤال با متن HTML',
            '<p>موتور جاروبرقی <strong>بوی سوختگی</strong> می‌دهد.</p><script>alert(1)</script>'
        );

        $description = $this->describe($question);

        $this->assertStringNotContainsString('<', $description);
        $this->assertStringContainsString('بوی سوختگی', $description);
    }

    /**
     * سؤالِ بی‌متن هنوز به site_description می‌افتد — رفتارِ آگاهانه، نه باگ.
     *
     * fallback خطِ ۲۱۸ عمداً حذف نشده: برای بدنهٔ واقعاً خالی، توضیحِ
     * سراسری بهتر از تگِ خالی است. این تست آن تصمیم را مستند می‌کند تا
     * تغییرِ آینده‌اش آگاهانه باشد.
     */
    public function test_an_empty_body_still_falls_back_to_the_site_description(): void
    {
        $question = $this->question('empty-body', 'سؤال بدون متن', null);

        $this->assertSame(self::SITE_DESCRIPTION, $this->describe($question));
    }
}
