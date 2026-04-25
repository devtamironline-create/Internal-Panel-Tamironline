<?php

namespace Modules\CRM\Console\Importers;

/**
 * Importer مشتری: کاربران WP با role=customer → جدول crm_customers.
 *
 * ID وردپرس عیناً حفظ می‌شود تا شماره اشتراک (id + 10000) ثابت بماند.
 * مشتری‌های موجود (تطابق روی mobile) به‌روزرسانی می‌شوند، تکراری ساخته
 * نمی‌شود → اجرای مجدد ایمن است.
 *
 * مرجع متاهای WP: libs/customers.php → role, first_name, mobile, phone
 */
class CustomerImporter extends AbstractImporter
{
    public function name(): string
    {
        return 'customers';
    }

    public function run(int $chunk): array
    {
        $usermetaTable = $this->wpTable('usermeta');
        $usersTable = $this->wpTable('users');

        // فقط user_id هایی که role=customer دارند
        $totalQuery = $this->legacy()
            ->table($usermetaTable)
            ->where('meta_key', 'role')
            ->where('meta_value', 'customer');

        $total = (clone $totalQuery)->count();
        $this->output->writeln("<info>یافت شد: {$total} مشتری در WP</info>");

        if ($total === 0) {
            return ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // chunk بر اساس user_id
        $totalQuery->select('user_id')
            ->orderBy('user_id')
            ->chunk($chunk, function ($rows) use (&$created, &$updated, &$skipped, $bar, $usersTable) {
                $ids = $rows->pluck('user_id')->all();

                // نام کاربری (موبایل) از wp_users
                $usernames = $this->legacy()
                    ->table($usersTable)
                    ->whereIn('ID', $ids)
                    ->pluck('user_login', 'ID');

                // متاهای موردنیاز را یک‌جا بگیریم تا کوئری به‌ازای هر کاربر کم شود
                $metas = $this->legacy()
                    ->table($this->wpTable('usermeta'))
                    ->whereIn('user_id', $ids)
                    ->whereIn('meta_key', ['first_name', 'mobile', 'phone'])
                    ->select('user_id', 'meta_key', 'meta_value')
                    ->get()
                    ->groupBy('user_id');

                foreach ($ids as $userId) {
                    $meta = $metas->get($userId, collect())->keyBy('meta_key');
                    $mobile = (string) ($meta->get('mobile')->meta_value ?? $usernames[$userId] ?? '');
                    $mobile = trim($mobile);

                    if ($mobile === '') {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $payload = [
                        'mobile' => $mobile,
                        'first_name' => $meta->get('first_name')->meta_value ?? null,
                        'phone' => $meta->get('phone')->meta_value ?? null,
                        'updated_at' => now(),
                    ];

                    // اگر مشتری با همین id وجود دارد، فقط به‌روزرسانی کن.
                    // در غیر اینصورت اگر mobile موجود است، روی همان به‌روزرسانی.
                    // در غیر اینصورت ایجاد با id=userId برای حفظ شماره اشتراک.
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

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->output->newLine();

        return [
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
