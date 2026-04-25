<?php

namespace Modules\CRM\Console\Importers;

/**
 * Importer مشتری: کاربران WP با role=customer → جدول crm_customers.
 *
 * ID وردپرس عیناً حفظ می‌شود تا شماره اشتراک (id + 10000) ثابت بماند.
 * مشتری‌های موجود (تطابق روی id یا mobile) به‌روزرسانی می‌شوند، تکراری
 * ساخته نمی‌شود → اجرای مجدد ایمن است.
 *
 * مرجع متاهای WP: libs/customers.php → role, first_name, mobile, phone
 */
class CustomerImporter extends AbstractImporter
{
    public function name(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return 'مشتری‌ها';
    }

    public function totalCount(): int
    {
        return $this->customerIdsQuery()->count();
    }

    public function processBatch(int $offset, int $limit): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $processed = 0;

        $rows = $this->customerIdsQuery()
            ->orderBy('user_id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return compact('processed', 'created', 'updated', 'skipped');
        }

        $ids = $rows->pluck('user_id')->all();

        // نام کاربری (موبایل) از wp_users
        $usernames = $this->legacy()
            ->table('users')
            ->whereIn('ID', $ids)
            ->pluck('user_login', 'ID');

        // متاهای موردنیاز را یک‌جا بگیریم
        $metas = $this->legacy()
            ->table('usermeta')
            ->whereIn('user_id', $ids)
            ->whereIn('meta_key', ['first_name', 'mobile', 'phone'])
            ->select('user_id', 'meta_key', 'meta_value')
            ->get()
            ->groupBy('user_id');

        foreach ($ids as $userId) {
            $userId = (int) $userId;
            $meta = $metas->get($userId, collect())->keyBy('meta_key');
            $mobileRaw = trim((string) ($meta->get('mobile')->meta_value ?? $usernames[$userId] ?? ''));

            // در WP بعضی مشتریان چند شماره را با خط‌تیره/فاصله/ویرگول چسبانده‌اند
            // (مثلاً «09126711068-09129317908»). اولین مقدار را به‌عنوان mobile و
            // در صورت خالی‌بودن phone، دومی را به phone منتقل می‌کنیم.
            $parts = preg_split('/[\s\-,،\/]+/u', $mobileRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $mobile = trim((string) ($parts[0] ?? ''));
            $secondary = isset($parts[1]) ? trim((string) $parts[1]) : null;

            if ($mobile === '') {
                $skipped++;
                $processed++;
                continue;
            }

            $phoneFromMeta = $meta->get('phone')->meta_value ?? null;

            $payload = [
                'mobile' => $mobile,
                'first_name' => $meta->get('first_name')->meta_value ?? null,
                'phone' => $phoneFromMeta ?: $secondary,
                'updated_at' => now(),
            ];

            $existsById = $this->target()->table('crm_customers')->where('id', $userId)->exists();

            if ($existsById) {
                $this->target()->table('crm_customers')->where('id', $userId)->update($payload);
                $updated++;
            } elseif ($this->target()->table('crm_customers')->where('mobile', $mobile)->exists()) {
                $this->target()->table('crm_customers')->where('mobile', $mobile)->update($payload);
                $updated++;
            } else {
                $this->target()->table('crm_customers')->insert(array_merge($payload, [
                    'id' => $userId,
                    'created_at' => now(),
                ]));
                $created++;
            }

            $processed++;
        }

        return compact('processed', 'created', 'updated', 'skipped');
    }

    /** کوئری user_idهایی که role=customer دارند. */
    protected function customerIdsQuery()
    {
        return $this->legacy()
            ->table('usermeta')
            ->where('meta_key', 'role')
            ->where('meta_value', 'customer')
            ->select('user_id');
    }
}
