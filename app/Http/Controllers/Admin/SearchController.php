<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Task\Models\Task;
use Modules\Task\Models\Team;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        // Search Staff
        if (auth()->user()->can('view-staff')) {
            $staff = User::where('is_staff', true)
                ->where(function($q) use ($query) {
                    $q->where('first_name', 'like', "%{$query}%")
                      ->orWhere('last_name', 'like', "%{$query}%")
                      ->orWhere('mobile', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get();
            
            foreach ($staff as $user) {
                $results[] = [
                    'type' => 'staff',
                    'icon' => 'user',
                    'title' => $user->full_name,
                    'subtitle' => 'پرسنل',
                    'url' => route('admin.staff.edit', $user->id),
                ];
            }
        }

        // Search Tasks
        if (auth()->user()->can('view-tasks')) {
            $tasks = Task::where('title', 'like', "%{$query}%")
                ->limit(5)
                ->get();
            
            foreach ($tasks as $task) {
                $results[] = [
                    'type' => 'task',
                    'icon' => 'task',
                    'title' => $task->title,
                    'subtitle' => 'تسک - ' . $task->status_label,
                    'url' => route('tasks.show', $task->id),
                ];
            }
        }

        // Search Teams
        if (auth()->user()->can('manage-teams')) {
            $teams = Team::where('name', 'like', "%{$query}%")
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

        // Add quick commands
        $commands = [
            ['cmd' => 'تسک جدید', 'url' => route('tasks.create')],
            ['cmd' => 'مرخصی جدید', 'url' => route('leave.create')],
            ['cmd' => 'داشبورد', 'url' => route('admin.dashboard')],
            ['cmd' => 'پیام‌رسان', 'url' => route('admin.messenger')],
        ];

        foreach ($commands as $cmd) {
            if (str_contains($cmd['cmd'], $query)) {
                $results[] = [
                    'type' => 'command',
                    'icon' => 'command',
                    'title' => $cmd['cmd'],
                    'subtitle' => 'دستور',
                    'url' => $cmd['url'],
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
