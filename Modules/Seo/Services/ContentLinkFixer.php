<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\LinkFixChange;
use Modules\Seo\Models\LinkFixRule;

/**
 * موتورِ اصلاحِ لینک‌های داخلی در سطحِ دیتابیس — روی چهار نوع محتوا:
 * مقالات، دستگاه‌ها، برندها، صفحاتِ ترکیبی.
 *
 * اصولِ ایمنی (پروداکشن):
 *  - scan هیچ چیزی را نمی‌نویسد؛ apply فقط با تأییدِ ادمین.
 *  - جایگزینی boundary-safe است: URL فقط وقتی عوض می‌شود که بعدش کاراکترِ
 *    ادامهٔ URL نباشد (پس /repair/washing-machine/ زیرمجموعهٔ URLِ بلندتر را خراب نمی‌کند).
 *  - unlink فقط تگِ <a> با همان href را باز می‌کند و متن/محتوای داخلش را نگه می‌دارد.
 *  - قبل از هر تغییر، مقدارِ کاملِ قبلیِ فیلد در seo_link_fix_changes ذخیره می‌شود (بازگردانی).
 *  - گاردِ self-link: اگر مقصدِ جدید همان صفحهٔ خودِ رکورد باشد، آن رکورد رد و گزارش می‌شود.
 */
class ContentLinkFixer
{
    /**
     * منابعِ محتوا: مدل + فیلدهای متنی/HTML + فیلدهای JSON + برچسب.
     *
     * @return array<string, array{model:class-string, string_fields:list<string>, json_fields:list<string>, label:string}>
     */
    public static function sources(): array
    {
        return [
            // prefilter=true یعنی پیش‌فیلترِ LIKE در SQL (برای جدول‌های بزرگ)؛
            // جدول‌های کوچکِ JSONدار (دستگاه/برند) کامل پیمایش می‌شوند چون
            // فارسیِ داخلِ JSON به‌صورت \uXXXX ذخیره شده و LIKE قابل‌اتکا نیست.
            'article' => [
                'model' => \Modules\Site\Models\Article::class,
                'string_fields' => ['content', 'description'],
                'json_fields' => [],
                'label' => 'title',
                'prefilter' => true,
            ],
            'device' => [
                'model' => \Modules\CRM\Models\Device::class,
                'string_fields' => ['description', 'subtitle', 'caption', 'warranty_text', 'support_info', 'cta_primary_url', 'cta_secondary_url'],
                'json_fields' => ['issues', 'faq', 'videos', 'service_steps'],
                'label' => 'name',
                'prefilter' => false,
            ],
            'brand' => [
                'model' => \Modules\CRM\Models\Brand::class,
                'string_fields' => ['description', 'subtitle', 'caption', 'tagline', 'warranty_text', 'support_info', 'cta_primary_url', 'cta_secondary_url'],
                'json_fields' => ['issues', 'faq', 'videos', 'why_us', 'stats'],
                'label' => 'name',
                'prefilter' => false,
            ],
            'combo' => [
                'model' => \Modules\CRM\Models\DeviceBrandPage::class,
                'string_fields' => ['description', 'subtitle', 'caption', 'title', 'cta_primary_url', 'cta_secondary_url'],
                'json_fields' => [],
                'label' => 'title',
                'prefilter' => true,
            ],
        ];
    }

    public const TYPE_LABELS = [
        'article' => 'مقاله',
        'device' => 'دستگاه',
        'brand' => 'برند',
        'combo' => 'صفحه ترکیبی',
    ];

    /**
     * واریانت‌های یک URL که باید در محتوا پیدا شوند: http/https، با/بدونِ
     * اسلشِ انتهایی، و شکلِ درصد-انکدشده/دیکد‌شدهٔ مسیرِ فارسی.
     *
     * @return list<string>
     */
    public static function variants(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [];
        }

        $out = [];
        foreach ([$url, self::swapScheme($url)] as $u) {
            foreach ([rtrim($u, '/'), rtrim($u, '/').'/'] as $v) {
                $out[] = $v;
                $decoded = rawurldecode($v);
                if ($decoded !== $v) {
                    $out[] = $decoded;
                } else {
                    $encoded = self::encodePath($v);
                    if ($encoded !== $v) {
                        $out[] = $encoded;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    private static function swapScheme(string $url): string
    {
        if (str_starts_with($url, 'https://')) {
            return 'http://'.substr($url, 8);
        }
        if (str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, 7);
        }

        return $url;
    }

    /** انکدِ درصدیِ بخشِ مسیر (فقط کاراکترهای غیر ASCII) بدونِ دست‌زدن به / و query. */
    private static function encodePath(string $url): string
    {
        return preg_replace_callback('/[^\x00-\x7F]+/u', fn ($m) => rawurlencode($m[0]), $url) ?? $url;
    }

    /**
     * اسکنِ یک قاعده روی همهٔ منابع — بدونِ هیچ تغییری.
     *
     * @return array{matches: list<array{type:string,id:int,label:?string,field:string,count:int,excerpt:string}>, total:int, skipped_self:int}
     */
    public function scan(LinkFixRule $rule): array
    {
        $variants = self::variants($rule->old_url);
        $matches = [];
        $total = 0;
        $skippedSelf = 0;

        foreach (self::sources() as $type => $src) {
            $this->eachCandidate($src, $variants, function ($record) use (&$matches, &$total, &$skippedSelf, $rule, $src, $type, $variants) {
                if ($this->isSelfLink($type, $record, $rule)) {
                    $skippedSelf++;

                    return;
                }
                foreach ($this->recordFieldMatches($record, $src, $variants, $rule) as $m) {
                    $matches[] = array_merge($m, [
                        'type' => $type,
                        'id' => (int) $record->getKey(),
                        'label' => (string) ($record->{$src['label']} ?? ''),
                    ]);
                    $total += $m['count'];
                }
            });
        }

        $rule->update(['matches_count' => $total, 'scanned_at' => now()]);

        return ['matches' => $matches, 'total' => $total, 'skipped_self' => $skippedSelf];
    }

    /**
     * اعمالِ یک قاعده — با ذخیرهٔ نسخهٔ قبلیِ هر فیلد برای بازگردانی.
     *
     * @return array{applied:int, records:int, skipped_self:int}
     */
    public function apply(LinkFixRule $rule, ?int $userId = null): array
    {
        $variants = self::variants($rule->old_url);
        $applied = 0;
        $records = 0;
        $skippedSelf = 0;

        foreach (self::sources() as $type => $src) {
            $this->eachCandidate($src, $variants, function ($record) use (&$applied, &$records, &$skippedSelf, $rule, $src, $type, $variants) {
                if ($this->isSelfLink($type, $record, $rule)) {
                    $skippedSelf++;

                    return;
                }

                $dirty = [];   // field => [new_value, before_raw, occurrences]
                foreach ($src['string_fields'] as $field) {
                    $val = $record->{$field};
                    if (! is_string($val) || $val === '') {
                        continue;
                    }
                    [$new, $count] = $this->transformString($val, $variants, $rule);
                    if ($count > 0 && $new !== $val) {
                        $dirty[$field] = [$new, $val, $count];
                    }
                }
                foreach ($src['json_fields'] as $field) {
                    $val = $record->{$field};
                    if (! is_array($val) || $val === []) {
                        continue;
                    }
                    $count = 0;
                    $new = $this->transformJson($val, $variants, $rule, $count);
                    if ($count > 0) {
                        $dirty[$field] = [$new, json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $count];
                    }
                }

                if ($dirty === []) {
                    return;
                }

                DB::transaction(function () use ($record, $dirty, $rule, $src, $type, &$applied) {
                    foreach ($dirty as $field => [$new, $before, $count]) {
                        LinkFixChange::create([
                            'rule_id' => $rule->id,
                            'entity_type' => $type,
                            'entity_id' => $record->getKey(),
                            'entity_label' => mb_substr((string) ($record->{$src['label']} ?? ''), 0, 300),
                            'field' => $field,
                            'before' => is_string($before) ? $before : (string) $before,
                            'occurrences' => $count,
                            'created_at' => now(),
                        ]);
                        $record->{$field} = $new;
                        $applied += $count;
                    }
                    $record->save();
                });
                $records++;
            });
        }

        $rule->update([
            'status' => 'applied',
            'applied_count' => $applied,
            'applied_at' => now(),
            'applied_by' => $userId,
        ]);

        return ['applied' => $applied, 'records' => $records, 'skipped_self' => $skippedSelf];
    }

    /**
     * بازگردانیِ یک تغییر: مقدارِ قبلیِ فیلد عیناً برگردانده می‌شود.
     * (اگر بعد از اعمال، رکورد دستی ویرایش شده باشد، آن ویرایش هم بازنویسی می‌شود —
     * در UI هشدار داده می‌شود.)
     */
    public function revert(LinkFixChange $change): bool
    {
        $src = self::sources()[$change->entity_type] ?? null;
        if (! $src) {
            return false;
        }
        $record = $src['model']::find($change->entity_id);
        if (! $record) {
            return false;
        }

        $field = $change->field;
        $value = $change->before;
        if (in_array($field, $src['json_fields'], true)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        $record->{$field} = $value;
        $record->save();
        $change->update(['reverted_at' => now()]);

        return true;
    }

    // ─── اسکن/اعمالِ تک‌گذر (برای «همه» — بهینه برای پروداکشن) ──────
    //
    // چرا: اسکنِ per-rule برای هر قاعده یک LIKE روی longTextِ کلِ مقالات
    // می‌زند؛ با ~۲۹۰ قاعده یعنی ~۲۹۰ فول‌اسکن → timeout روی هاست. این‌جا هر
    // جدول فقط «یک بار» chunk-به-chunk خوانده می‌شود و همهٔ قواعد هم‌زمان با
    // regexِ ترکیبی چک می‌شوند.

    /**
     * اسکنِ همهٔ قواعدِ pending در یک گذر. matches_count هر قاعده به‌روزرسانی می‌شود.
     *
     * @return array{scanned:int, with_matches:int}
     */
    public function scanAllPending(): array
    {
        $rules = LinkFixRule::pending()->get();
        if ($rules->isEmpty()) {
            return ['scanned' => 0, 'with_matches' => 0];
        }

        $matcher = $this->buildMatcher($rules);
        $counts = array_fill_keys($rules->pluck('id')->all(), 0);

        foreach (self::sources() as $type => $src) {
            $this->eachRecordLight($src, function ($record) use (&$counts, $matcher, $src, $type) {
                $this->tallyRecord($record, $src, $type, $matcher, function (int $ruleId, int $n) use (&$counts) {
                    $counts[$ruleId] += $n;
                });
            });
        }

        $withMatches = 0;
        $now = now();
        foreach ($rules as $rule) {
            $total = $counts[$rule->id] ?? 0;
            $rule->update(['matches_count' => $total, 'scanned_at' => $now]);
            if ($total > 0) {
                $withMatches++;
            }
        }

        return ['scanned' => $rules->count(), 'with_matches' => $withMatches];
    }

    /**
     * اعمالِ همهٔ قواعدِ آماده در یک گذر: اول رکوردهای دارای مورد پیدا می‌شوند
     * (سبک)، بعد هر رکورد یک بار لود، همهٔ قواعدِ مرتبط رویش اجرا و یک بار
     * ذخیره می‌شود.
     *
     * @return array{applied:int, records:int, rules:int}
     */
    public function applyAllPending(?int $userId = null): array
    {
        $rules = LinkFixRule::pending()
            ->where('needs_review', false)
            ->where('matches_count', '>', 0)
            ->get()
            ->keyBy('id');
        if ($rules->isEmpty()) {
            return ['applied' => 0, 'records' => 0, 'rules' => 0];
        }

        $matcher = $this->buildMatcher($rules);

        // گذرِ سبک: نگاشتِ [type][record_id][rule_id] => true
        $hits = [];
        foreach (self::sources() as $type => $src) {
            $this->eachRecordLight($src, function ($record) use (&$hits, $matcher, $src, $type) {
                $this->tallyRecord($record, $src, $type, $matcher, function (int $ruleId) use (&$hits, $type, $record) {
                    $hits[$type][$record->getKey()][$ruleId] = true;
                });
            });
        }

        $appliedTotal = 0;
        $recordsTouched = 0;
        $rulesApplied = [];  // rule_id => occurrences

        foreach ($hits as $type => $records) {
            $src = self::sources()[$type];
            foreach ($records as $recordId => $ruleIds) {
                $record = $src['model']::find($recordId);
                if (! $record) {
                    continue;
                }

                $dirtyChanges = []; // [field, before, occurrences, rule_id]
                foreach (array_keys($ruleIds) as $ruleId) {
                    $rule = $rules[$ruleId] ?? null;
                    if (! $rule || $this->isSelfLink($type, $record, $rule)) {
                        continue;
                    }
                    $variants = self::variants($rule->old_url);

                    foreach ($src['string_fields'] as $field) {
                        $val = $record->{$field};
                        if (! is_string($val) || $val === '') {
                            continue;
                        }
                        [$new, $count] = $this->transformString($val, $variants, $rule);
                        if ($count > 0 && $new !== $val) {
                            $dirtyChanges[] = [$field, $val, $count, $ruleId];
                            $record->{$field} = $new;
                        }
                    }
                    foreach ($src['json_fields'] as $field) {
                        $val = $record->{$field};
                        if (! is_array($val) || $val === []) {
                            continue;
                        }
                        $count = 0;
                        $new = $this->transformJson($val, $variants, $rule, $count);
                        if ($count > 0) {
                            $dirtyChanges[] = [$field, json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $count, $ruleId];
                            $record->{$field} = $new;
                        }
                    }
                }

                if ($dirtyChanges === []) {
                    continue;
                }

                DB::transaction(function () use ($record, $dirtyChanges, $src, $type, &$appliedTotal, &$rulesApplied) {
                    foreach ($dirtyChanges as [$field, $before, $count, $ruleId]) {
                        LinkFixChange::create([
                            'rule_id' => $ruleId,
                            'entity_type' => $type,
                            'entity_id' => $record->getKey(),
                            'entity_label' => mb_substr((string) ($record->{$src['label']} ?? ''), 0, 300),
                            'field' => $field,
                            'before' => (string) $before,
                            'occurrences' => $count,
                            'created_at' => now(),
                        ]);
                        $appliedTotal += $count;
                        $rulesApplied[$ruleId] = ($rulesApplied[$ruleId] ?? 0) + $count;
                    }
                    $record->save();
                });
                $recordsTouched++;
            }
        }

        // همهٔ قواعدِ شرکت‌داده‌شده «اعمال‌شده» علامت می‌خورند (حتی اگر قاعدهٔ
        // هم‌پوشانِ http/https عملاً موردی برایش نمانده باشد).
        foreach ($rules as $rule) {
            $rule->update([
                'status' => 'applied',
                'applied_count' => $rulesApplied[$rule->id] ?? 0,
                'applied_at' => now(),
                'applied_by' => $userId,
            ]);
        }

        return ['applied' => $appliedTotal, 'records' => $recordsTouched, 'rules' => count($rulesApplied)];
    }

    /**
     * ساختِ تطبیق‌گرِ ترکیبی: نگاشتِ واریانت→قواعد + الگوهای regexِ chunkشده +
     * گاردِ self-link برای مقالات.
     *
     * @param  \Illuminate\Support\Collection<int, LinkFixRule>  $rules
     * @return array{map: array<string, list<int>>, patterns: list<string>, self_guard: array<int, string>}
     */
    private function buildMatcher($rules): array
    {
        $map = [];
        $selfGuard = [];
        foreach ($rules as $rule) {
            foreach (self::variants($rule->old_url) as $v) {
                $map[$v][] = $rule->id;
            }
            if ($rule->action === 'replace' && $rule->new_url) {
                $path = rtrim(rawurldecode((string) (parse_url($rule->new_url, PHP_URL_PATH) ?: '')), '/');
                if (str_starts_with($path, '/blog/')) {
                    $selfGuard[$rule->id] = rawurldecode(substr($path, strlen('/blog/')));
                }
            }
        }

        $patterns = [];
        foreach (array_chunk(array_keys($map), 150) as $chunk) {
            // backslash در boundary لازم است: در blobِ JSONشده، URL داخلِ \" است.
            $patterns[] = '~(?:'.implode('|', array_map(fn ($v) => preg_quote($v, '~'), $chunk)).')(?=["\'\s<>#?)\\\\]|$)~u';
        }

        return ['map' => $map, 'patterns' => $patterns, 'self_guard' => $selfGuard];
    }

    /** پیمایشِ سبکِ رکوردهای یک منبع: فقط ستون‌های لازم، chunkبندی‌شده. */
    private function eachRecordLight(array $src, callable $callback): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $src['model'];
        $columns = array_values(array_unique(array_merge(
            [$model->getKeyName(), $src['label']],
            in_array('slug', $model->getFillable(), true) ? ['slug'] : [],
            $src['string_fields'],
            $src['json_fields'],
        )));

        $src['model']::query()->select($columns)->chunkById(100, function ($records) use ($callback) {
            foreach ($records as $record) {
                $callback($record);
            }
        });
    }

    /** شمارشِ برخوردهای همهٔ قواعد در یک رکورد (با regexِ ترکیبی). */
    private function tallyRecord($record, array $src, string $type, array $matcher, callable $onHit): void
    {
        $texts = [];
        foreach ($src['string_fields'] as $field) {
            $val = $record->{$field};
            if (is_string($val) && $val !== '') {
                $texts[] = $val;
            }
        }
        foreach ($src['json_fields'] as $field) {
            $val = $record->{$field};
            if (is_array($val) && $val !== []) {
                $texts[] = (string) json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if ($texts === []) {
            return;
        }

        $blob = implode("\n", $texts);
        foreach ($matcher['patterns'] as $pattern) {
            if (! preg_match_all($pattern, $blob, $m)) {
                continue;
            }
            $found = array_count_values($m[0]);
            foreach ($found as $variant => $n) {
                foreach ($matcher['map'][$variant] ?? [] as $ruleId) {
                    // گاردِ self-link برای مقالات
                    if ($type === 'article' && isset($matcher['self_guard'][$ruleId])
                        && rawurldecode((string) ($record->slug ?? '')) === $matcher['self_guard'][$ruleId]) {
                        continue;
                    }
                    $onHit($ruleId, (int) $n);
                }
            }
        }
    }

    // ─── هسته‌ی تبدیل ─────────────────────────────────────────────

    /**
     * تبدیلِ یک رشته (HTML یا URLِ خالص) طبقِ قاعده.
     *
     * @param  list<string>  $variants
     * @return array{0:string,1:int} [مقدارِ جدید، تعدادِ تغییر]
     */
    public function transformString(string $value, array $variants, LinkFixRule $rule): array
    {
        $count = 0;

        // مقدارِ فیلد دقیقاً خودِ URL است (مثلِ cta_url)
        if (in_array(trim($value), $variants, true)) {
            if ($rule->action === 'replace' && $rule->new_url) {
                return [$rule->new_url, 1];
            }

            return [$value, 0]; // unlink روی فیلدِ URL خالص معنا ندارد — دست نمی‌زنیم
        }

        $alternation = implode('|', array_map(fn ($v) => preg_quote($v, '~'), $variants));

        if ($rule->action === 'unlink') {
            // حذفِ تگِ <a> با همین href و حفظِ محتوای داخلی.
            $pattern = '~<a\b[^>]*href=["\'](?:'.$alternation.')["\'][^>]*>(.*?)</a>~isu';
            $new = preg_replace($pattern, '$1', $value, -1, $count);

            return [$new ?? $value, (int) $count];
        }

        if (! $rule->new_url) {
            return [$value, 0];
        }

        // جایگزینی boundary-safe: بعد از URL نباید کاراکترِ ادامهٔ مسیر بیاید.
        $pattern = '~(?:'.$alternation.')(?=["\'\s<>#?)]|$)~u';
        $new = preg_replace($pattern, $rule->new_url, $value, -1, $count);

        return [$new ?? $value, (int) $count];
    }

    /**
     * پیمایشِ بازگشتیِ آرایهٔ JSON و تبدیلِ رشته‌های برگ.
     *
     * @param  list<string>  $variants
     */
    private function transformJson(array $data, array $variants, LinkFixRule $rule, int &$count): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->transformJson($v, $variants, $rule, $count);
            } elseif (is_string($v) && $v !== '') {
                [$new, $c] = $this->transformString($v, $variants, $rule);
                if ($c > 0) {
                    $data[$k] = $new;
                    $count += $c;
                }
            }
        }

        return $data;
    }

    // ─── کمکی‌ها ─────────────────────────────────────────────────

    /**
     * پیمایشِ رکوردهای کاندیدِ یک منبع (پیش‌فیلترِ LIKE روی فیلدهای متنی + JSON خام).
     */
    private function eachCandidate(array $src, array $variants, callable $callback): void
    {
        if ($variants === []) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $src['model'];
        $table = $model->getTable();

        $query = $src['model']::query();

        if (! empty($src['prefilter'])) {
            // پیش‌فیلترِ LIKE با «بلندترین سگمنتِ مسیر» — عمداً بدونِ escape:
            // کاراکترِ % به‌صورتِ wildcard عمل می‌کند و صرفاً مجموعه را «گشادتر»
            // می‌کند (سوپرسِتِ امن)؛ تطبیقِ دقیق همیشه در PHP انجام می‌شود.
            $fields = array_merge($src['string_fields'], $src['json_fields']);
            $needles = [];
            foreach ($variants as $v) {
                $path = (string) preg_replace('~^https?://[^/]+~', '', $v);
                $segments = array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== '');
                if ($segments === []) {
                    $needles[$v] = true;

                    continue;
                }
                usort($segments, fn ($a, $b) => strlen($b) <=> strlen($a));
                $needles[$segments[0]] = true;
            }

            $query->where(function ($q) use ($fields, $needles, $table) {
                foreach ($fields as $f) {
                    foreach (array_keys($needles) as $n) {
                        $q->orWhere($table.'.'.$f, 'like', '%'.$n.'%');
                    }
                }
            });
        }

        foreach ($query->cursor() as $record) {
            $callback($record);
        }
    }

    /**
     * @return list<array{field:string,count:int,excerpt:string}>
     */
    private function recordFieldMatches($record, array $src, array $variants, LinkFixRule $rule): array
    {
        $out = [];
        foreach ($src['string_fields'] as $field) {
            $val = $record->{$field};
            if (! is_string($val) || $val === '') {
                continue;
            }
            [, $count] = $this->transformString($val, $variants, $rule);
            if ($count > 0) {
                $out[] = ['field' => $field, 'count' => $count, 'excerpt' => $this->excerpt($val, $variants)];
            }
        }
        foreach ($src['json_fields'] as $field) {
            $val = $record->{$field};
            if (! is_array($val) || $val === []) {
                continue;
            }
            $count = 0;
            $this->transformJson($val, $variants, $rule, $count);
            if ($count > 0) {
                $raw = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $out[] = ['field' => $field, 'count' => $count, 'excerpt' => $this->excerpt($raw, $variants)];
            }
        }

        return $out;
    }

    private function excerpt(string $value, array $variants): string
    {
        foreach ($variants as $v) {
            $pos = mb_strpos($value, $v);
            if ($pos !== false) {
                $start = max(0, $pos - 60);

                return ($start > 0 ? '…' : '').mb_substr($value, $start, 60 + mb_strlen($v) + 60).'…';
            }
        }

        return mb_substr($value, 0, 120).'…';
    }

    /**
     * گاردِ self-link: مقصدِ جدید نباید صفحهٔ خودِ رکورد باشد (فعلاً فقط مقاله —
     * الگوی /blog/{slug}).
     */
    private function isSelfLink(string $type, $record, LinkFixRule $rule): bool
    {
        if ($rule->action !== 'replace' || ! $rule->new_url || $type !== 'article') {
            return false;
        }
        $path = rawurldecode((string) (parse_url($rule->new_url, PHP_URL_PATH) ?: ''));
        $path = rtrim($path, '/');

        return $path === '/blog/'.rawurldecode((string) $record->slug);
    }
}
