<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * تنها راهِ روشن/خاموش‌کردن دسترسی «صندوق سرمایه».
 *
 *   php artisan investment:access grant  09121234567
 *   php artisan investment:access revoke user@example.com
 *   php artisan investment:access list
 */
class InvestmentAccessCommand extends Command
{
    protected $signature = 'investment:access {action : grant | revoke | list} {user? : موبایل یا ایمیل کاربر}';

    protected $description = 'مدیریت دسترسی صندوق سرمایه (فقط از طریق همین کامند)';

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'list') {
            $users = User::where('investment_access', true)->get(['id', 'first_name', 'last_name', 'mobile', 'email']);
            if ($users->isEmpty()) {
                $this->info('هیچ کاربری دسترسی صندوق سرمایه ندارد.');

                return self::SUCCESS;
            }
            $this->table(
                ['ID', 'نام', 'موبایل', 'ایمیل'],
                $users->map(fn ($u) => [$u->id, trim($u->first_name.' '.$u->last_name), $u->mobile, $u->email])
            );

            return self::SUCCESS;
        }

        if (! in_array($action, ['grant', 'revoke'], true)) {
            $this->error('اکشن نامعتبر — grant یا revoke یا list.');

            return self::FAILURE;
        }

        $identifier = trim((string) $this->argument('user'));
        if ($identifier === '') {
            $this->error('موبایل یا ایمیل کاربر را بدهید.');

            return self::FAILURE;
        }

        $user = User::where('mobile', $identifier)->orWhere('email', $identifier)->first();
        if (! $user) {
            $this->error('کاربری با این موبایل/ایمیل پیدا نشد: '.$identifier);

            return self::FAILURE;
        }

        $user->forceFill(['investment_access' => $action === 'grant'])->save();

        $this->info(($action === 'grant' ? 'دسترسی صندوق سرمایه داده شد به: ' : 'دسترسی صندوق سرمایه گرفته شد از: ')
            .trim($user->first_name.' '.$user->last_name).' ('.$identifier.')');

        return self::SUCCESS;
    }
}
