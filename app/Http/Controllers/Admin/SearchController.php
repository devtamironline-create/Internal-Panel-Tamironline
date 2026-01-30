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
                        ->where(function($q) use ($query) {
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
                    Log::warning('Search staff error: ' . $e->getMessage());
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
                                'subtitle' => 'تسک - ' . ($task->status_label ?? $task->status),
                                'url' => route('tasks.show', $task->id),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Search tasks error: ' . $e->getMessage());
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
                    Log::warning('Search teams error: ' . $e->getMessage());
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
                if (!$matched) {
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
            Log::error('Search error: ' . $e->getMessage());
            return response()->json(['results' => [], 'error' => 'خطا در جستجو']);
        }
    }
}
