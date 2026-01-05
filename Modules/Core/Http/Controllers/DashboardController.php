<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\LeaveRequest;
use Modules\Attendance\Models\EmployeeSetting;
use Modules\Task\Models\Task;
use Modules\Task\Models\Team;

class DashboardController extends Controller
{
    public function admin()
    {
        $user = auth()->user();
        $stats = [];

        // Staff stats (for managers/admins)
        if ($user->can('view-staff') || $user->can('manage-staff') || $user->can('manage-permissions')) {
            $stats['staff_count'] = User::staff()->count();
            $stats['active_staff'] = User::staff()->where('is_active', true)->count();
        }

        // Attendance stats (for user's own attendance)
        if ($user->can('view-attendance') || $user->can('manage-attendance')) {
            $todayAttendance = Attendance::getTodayForUser($user->id);
            $stats['attendance'] = [
                'checked_in' => $todayAttendance && $todayAttendance->check_in,
                'checked_out' => $todayAttendance && $todayAttendance->check_out,
                'check_in_time' => $todayAttendance?->check_in,
                'work_hours' => $todayAttendance?->work_hours ?? '-',
            ];

            // Monthly attendance for current user
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();
            $stats['monthly_attendance'] = [
                'present_days' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->where('status', Attendance::STATUS_PRESENT)
                    ->count(),
                'absent_days' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->where('status', Attendance::STATUS_ABSENT)
                    ->count(),
                'leave_days' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->where('status', Attendance::STATUS_LEAVE)
                    ->count(),
            ];
        }

        // Attendance management stats
        if ($user->can('manage-attendance')) {
            $stats['attendance_management'] = [
                'today_present' => Attendance::where('date', today())
                    ->where('status', Attendance::STATUS_PRESENT)
                    ->count(),
                'today_checked_in' => Attendance::where('date', today())
                    ->whereNotNull('check_in')
                    ->count(),
                'today_incomplete' => Attendance::where('date', today())
                    ->whereNotNull('check_in')
                    ->whereNull('check_out')
                    ->count(),
            ];
        }

        // Leave stats (for user's own leave)
        if ($user->can('view-leave') || $user->can('request-leave')) {
            $employeeSettings = EmployeeSetting::getOrCreate($user->id);
            $stats['leave'] = [
                'annual_balance' => $employeeSettings->annual_leave_balance ?? 0,
                'sick_balance' => $employeeSettings->sick_leave_balance ?? 0,
                'pending_requests' => LeaveRequest::where('user_id', $user->id)
                    ->where('status', LeaveRequest::STATUS_PENDING)
                    ->count(),
            ];
        }

        // Leave management stats
        if ($user->can('manage-leave')) {
            $stats['leave_management'] = [
                'pending_count' => LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)->count(),
                'today_on_leave' => Attendance::where('date', today())
                    ->where('status', Attendance::STATUS_LEAVE)
                    ->count(),
            ];
        }

        // Task stats (for user's own tasks)
        if ($user->can('view-tasks') || $user->can('create-tasks') || $user->can('manage-tasks')) {
            $userTasks = Task::getUserStats($user->id);
            $stats['tasks'] = [
                'my_total' => $userTasks['total'],
                'my_completed' => $userTasks['completed'],
                'my_in_progress' => $userTasks['in_progress'],
                'my_overdue' => $userTasks['overdue'],
            ];
        }

        // Task management stats
        if ($user->can('manage-tasks')) {
            $stats['task_management'] = [
                'total_tasks' => Task::count(),
                'completed_tasks' => Task::where('status', Task::STATUS_DONE)->count(),
                'overdue_tasks' => Task::overdue()->count(),
                'teams_count' => Team::count(),
            ];
        }

        // Team stats
        if ($user->can('view-teams') || $user->can('manage-teams')) {
            $stats['teams'] = Team::withCount('members', 'tasks')->get();
        }

        return view('admin.dashboard', compact('stats'));
    }
}
