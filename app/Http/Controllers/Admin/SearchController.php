<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        try {
            $query = trim($request->get('q', ''));

            if (mb_strlen($query) < 2) {
                return response()->json(['results' => []]);
            }

            $results = [];
            $user = auth()->user();

            // Search Staff
            if ($user->can('view-staff') || $user->can('manage-permissions')) {
                try {
                    $staff = User::where('is_staff', true)
                        ->where(function ($q) use ($query) {
                            $q->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('mobile', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        })
                        ->limit(5)
                        ->get();

                    foreach ($staff as $staffUser) {
                        $results[] = [
                            'type' => 'staff',
                            'icon' => 'user',
                            'title' => $staffUser->full_name,
                            'subtitle' => 'پرسنل',
                            'url' => route('admin.staff.edit', $staffUser->id),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Search staff error: '.$e->getMessage());
                }
            }

            // Search Tasks
            if ($user->can('view-tasks') || $user->can('manage-permissions')) {
                try {
                    if (class_exists(\Modules\Task\Models\Task::class)) {
                        $tasks = \Modules\Task\Models\Task::where('title', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->limit(5)
                            ->get();

                        foreach ($tasks as $task) {
                            $results[] = [
                                'type' => 'task',
                                'icon' => 'task',
                                'title' => $task->title,
                                'subtitle' => 'تسک - '.($task->status_label ?? $task->status),
                                'url' => route('tasks.show', $task->id),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Search tasks error: '.$e->getMessage());
                }
            }

            // Search Teams
            if ($user->can('manage-teams') || $user->can('manage-permissions')) {
                try {
                    if (class_exists(\Modules\Task\Models\Team::class)) {
                        $teams = \Modules\Task\Models\Team::where('name', 'like', "%{$query}%")
                            ->limit(3)
                            ->get();

                        foreach ($teams as $team) {
                            $results[] = [
                                'type' => 'team',
                                'icon' => 'team',
                                'title' => $team->name,
                                'subtitle' => 'تیم',
                                'url' => route('teams.edit', $team->id),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Search teams error: '.$e->getMessage());
                }
            }

            // Normalized query — ارقامِ فارسی/عربی → انگلیسی تا موبایل/شماره‌ها
            // با هر نوع رقم پیدا شوند (نام‌ها بدونِ رقم دست‌نخورده می‌مانند).
            $nq = fa_to_en_digits($query);

            // Search Customers (name + mobile)
            if ($user->can('view-crm-customers') || $user->can('manage-permissions')) {
                try {
                    $customers = \Modules\CRM\Models\Customer::where(function ($q) use ($nq) {
                        $q->where('first_name', 'like', "%{$nq}%")
                            ->orWhere('last_name', 'like', "%{$nq}%")
                            ->orWhere('mobile', 'like', "%{$nq}%");
                    })->limit(5)->get();

                    foreach ($customers as $c) {
                        $results[] = [
                            'type' => 'customer',
                            'icon' => 'user',
                            'title' => $c->display_name,
                            'subtitle' => 'مشتری'.($c->mobile ? ' — '.$c->mobile : ''),
                            'url' => route('crm.customers.show', $c->id),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Search customers error: '.$e->getMessage());
                }
            }

            // Search Orders (order code / customer)
            if ($user->can('view-crm-orders') || $user->can('manage-permissions')) {
                try {
                    $orders = \Modules\CRM\Models\Order::search($nq)->latest('id')->limit(5)->get();
                    foreach ($orders as $o) {
                        $results[] = [
                            'type' => 'order',
                            'icon' => 'command',
                            'title' => $o->order_code,
                            'subtitle' => 'سفارش'.($o->customer_name ? ' — '.$o->customer_name : ''),
                            'url' => route('crm.orders.show', $o->id),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Search orders error: '.$e->getMessage());
                }
            }

            // Search Technicians (name + mobile)
            if ($user->can('view-crm-technicians') || $user->can('manage-permissions')) {
                try {
                    $techs = \Modules\CRM\Models\Technician::search($nq)->limit(5)->get();
                    foreach ($techs as $t) {
                        $results[] = [
                            'type' => 'technician',
                            'icon' => 'user',
                            'title' => $t->full_name,
                            'subtitle' => 'تکنسین'.($t->mobile ? ' — '.$t->mobile : ''),
                            'url' => route('crm.technicians.show', $t->id),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Search technicians error: '.$e->getMessage());
                }
            }

            // Search Invoices (invoice code / order code / customer)
            if ($user->can('view-crm-invoices') || $user->can('manage-permissions')) {
                try {
                    $invoices = \Modules\CRM\Models\Invoice::with('customer')
                        ->where(function ($q) use ($nq) {
                            $q->where('invoice_code', 'like', "%{$nq}%")
                                ->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', "%{$nq}%"))
                                ->orWhereHas('customer', fn ($c) => $c->where('mobile', 'like', "%{$nq}%")
                                    ->orWhere('first_name', 'like', "%{$nq}%")
                                    ->orWhere('last_name', 'like', "%{$nq}%"));
                        })->latest('id')->limit(5)->get();

                    foreach ($invoices as $inv) {
                        $results[] = [
                            'type' => 'invoice',
                            'icon' => 'command',
                            'title' => $inv->invoice_code,
                            'subtitle' => 'فاکتور'.($inv->customer?->display_name ? ' — '.$inv->customer->display_name : ''),
                            'url' => route('crm.invoices.show', $inv->id),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Search invoices error: '.$e->getMessage());
                }
            }

            // Add quick commands - always available
            $commands = [
                ['cmd' => 'تسک جدید', 'keywords' => ['تسک', 'جدید', 'task', 'new'], 'url' => route('tasks.create')],
                ['cmd' => 'مرخصی جدید', 'keywords' => ['مرخصی', 'جدید', 'leave'], 'url' => route('leave.create')],
                ['cmd' => 'داشبورد', 'keywords' => ['داشبورد', 'dashboard', 'خانه', 'home'], 'url' => route('admin.dashboard')],
                ['cmd' => 'پیام‌رسان', 'keywords' => ['پیام', 'چت', 'messenger', 'chat'], 'url' => route('admin.messenger')],
                ['cmd' => 'پرسنل', 'keywords' => ['پرسنل', 'کارمند', 'staff'], 'url' => route('admin.staff.index')],
                ['cmd' => 'حضور و غیاب', 'keywords' => ['حضور', 'غیاب', 'attendance'], 'url' => route('attendance.index')],
                ['cmd' => 'گزارش', 'keywords' => ['گزارش', 'report'], 'url' => route('tasks.reports.users')],
            ];

            foreach ($commands as $cmd) {
                $matched = mb_stripos($cmd['cmd'], $query) !== false;
                if (! $matched) {
                    foreach ($cmd['keywords'] as $keyword) {
                        if (mb_stripos($keyword, $query) !== false || mb_stripos($query, $keyword) !== false) {
                            $matched = true;
                            break;
                        }
                    }
                }

                if ($matched) {
                    $results[] = [
                        'type' => 'command',
                        'icon' => 'command',
                        'title' => $cmd['cmd'],
                        'subtitle' => 'دستور سریع',
                        'url' => $cmd['url'],
                    ];
                }
            }

            return response()->json(['results' => $results]);

        } catch (\Exception $e) {
            Log::error('Search error: '.$e->getMessage());

            return response()->json(['results' => [], 'error' => 'خطا در جستجو']);
        }
    }
}
