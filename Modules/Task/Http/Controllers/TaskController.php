<?php

namespace Modules\Task\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Task\Models\Task;
use Modules\Task\Models\TaskChecklist;
use Modules\Task\Models\TaskComment;
use Modules\Task\Models\Team;

class TaskController extends Controller
{
    /**
     * Kanban board view
     */
    public function index(Request $request)
    {
        $teams = Team::getActive();
        $currentTeam = null;
        $tasks = collect();

        if ($request->filled('team')) {
            $currentTeam = Team::where('slug', $request->team)->first();
        }

        if (!$currentTeam && $teams->isNotEmpty()) {
            $currentTeam = $teams->first();
        }

        if ($currentTeam) {
            $query = Task::with(['assignee', 'labels', 'checklists'])
                ->forTeam($currentTeam->id)
                ->mainTasks();

            // Filter by assignee
            if ($request->filled('assignee')) {
                $query->forUser($request->assignee);
            }

            // Filter by priority
            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            $tasks = $query->orderBy('sort_order')->orderBy('created_at', 'desc')->get();
        }

        // Group tasks by status for Kanban
        $columns = [
            'todo' => ['label' => 'در انتظار', 'color' => 'blue', 'tasks' => $tasks->where('status', 'todo')->values()],
            'in_progress' => ['label' => 'در حال انجام', 'color' => 'yellow', 'tasks' => $tasks->where('status', 'in_progress')->values()],
            'review' => ['label' => 'بررسی', 'color' => 'purple', 'tasks' => $tasks->where('status', 'review')->values()],
            'done' => ['label' => 'تکمیل شده', 'color' => 'green', 'tasks' => $tasks->where('status', 'done')->values()],
        ];

        // Get team members for filter
        $teamMembers = $currentTeam ? $currentTeam->members : collect();

        return view('task::index', compact('teams', 'currentTeam', 'columns', 'teamMembers'));
    }

    /**
     * My tasks view
     */
    public function myTasks()
    {
        $user = auth()->user();

        $tasks = Task::with(['team', 'labels', 'checklists'])
            ->forUser($user->id)
            ->mainTasks()
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('due_date')
            ->get();

        $stats = Task::getUserStats($user->id);

        $overdueTasks = Task::forUser($user->id)->overdue()->get();

        return view('task::my-tasks', compact('tasks', 'stats', 'overdueTasks'));
    }

    /**
     * Create task form
     */
    public function create(Request $request)
    {
        $teams = Team::getActive();
        $currentTeam = $request->filled('team')
            ? Team::where('slug', $request->team)->first()
            : $teams->first();

        $teamMembers = $currentTeam ? $currentTeam->members : collect();

        return view('task::create', compact('teams', 'currentTeam', 'teamMembers'));
    }

    /**
     * Store new task
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'team_id' => 'required|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|string',
            'checklist' => 'nullable|array',
            'checklist.*' => 'string|max:255',
        ], [
            'title.required' => 'عنوان تسک الزامی است',
            'team_id.required' => 'انتخاب تیم الزامی است',
        ]);

        // Convert Jalali date
        $dueDate = null;
        if ($request->filled('due_date')) {
            $dueDateStr = $this->persianToLatin($request->due_date);
            try {
                $dueDate = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $dueDateStr)->toCarbon();
            } catch (\Exception $e) {
                return back()->withErrors(['due_date' => 'فرمت تاریخ نامعتبر است'])->withInput();
            }
        }

        $task = Task::createTask([
            'title' => $request->title,
            'description' => $request->description,
            'team_id' => $request->team_id,
            'assigned_to' => $request->assigned_to,
            'priority' => $request->priority,
            'due_date' => $dueDate,
            'status' => 'todo',
        ], auth()->id());

        // Add checklist items
        if ($request->filled('checklist')) {
            foreach ($request->checklist as $index => $item) {
                if (!empty(trim($item))) {
                    $task->checklists()->create([
                        'title' => trim($item),
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        $team = Team::find($request->team_id);

        return redirect()->route('tasks.index', ['team' => $team->slug])
            ->with('success', 'تسک با موفقیت ایجاد شد');
    }

    /**
     * Show task details
     */
    public function show(Task $task)
    {
        $task->load(['team', 'creator', 'assignee', 'labels', 'checklists', 'comments.user', 'activities.user', 'attachments']);

        return view('task::show', compact('task'));
    }

    /**
     * Edit task form
     */
    public function edit(Task $task)
    {
        $teams = Team::getActive();
        $teamMembers = $task->team->members;

        return view('task::edit', compact('task', 'teams', 'teamMembers'));
    }

    /**
     * Update task
     */
    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|string',
        ]);

        // Convert Jalali date
        $dueDate = null;
        if ($request->filled('due_date')) {
            $dueDateStr = $this->persianToLatin($request->due_date);
            try {
                $dueDate = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $dueDateStr)->toCarbon();
            } catch (\Exception $e) {
                return back()->withErrors(['due_date' => 'فرمت تاریخ نامعتبر است'])->withInput();
            }
        }

        // Track changes
        $changes = [];
        if ($task->title !== $request->title) {
            $changes['title'] = ['old' => $task->title, 'new' => $request->title];
        }
        if ($task->priority !== $request->priority) {
            $changes['priority'] = ['old' => $task->priority, 'new' => $request->priority];
        }

        $task->update([
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to' => $request->assigned_to,
            'priority' => $request->priority,
            'due_date' => $dueDate,
        ]);

        // Log changes
        foreach ($changes as $field => $change) {
            $task->logActivity('updated', $field, $change['old'], $change['new']);
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'تسک با موفقیت ویرایش شد');
    }

    /**
     * Delete task
     */
    public function destroy(Task $task)
    {
        $teamSlug = $task->team->slug;
        $task->delete();

        return redirect()->route('tasks.index', ['team' => $teamSlug])
            ->with('success', 'تسک حذف شد');
    }

    /**
     * Update task status (AJAX)
     */
    public function updateStatus(Request $request, Task $task)
    {
        $request->validate([
            'status' => 'required|in:backlog,todo,in_progress,review,done',
        ]);

        $task->updateStatus($request->status, auth()->id());

        return response()->json([
            'success' => true,
            'task' => $task->fresh(),
        ]);
    }

    /**
     * Update task order (AJAX - for drag & drop)
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.status' => 'required|in:backlog,todo,in_progress,review,done',
            'tasks.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->tasks as $taskData) {
            $task = Task::find($taskData['id']);
            $oldStatus = $task->status;

            $task->update([
                'status' => $taskData['status'],
                'sort_order' => $taskData['sort_order'],
            ]);

            // Log status change if different
            if ($oldStatus !== $taskData['status']) {
                $task->logActivity('status_changed', 'status', $oldStatus, $taskData['status']);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Add comment
     */
    public function addComment(Request $request, Task $task)
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment = TaskComment::addComment($task, auth()->id(), $request->body);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $comment->load('user'),
            ]);
        }

        return back()->with('success', 'نظر اضافه شد');
    }

    /**
     * Toggle checklist item
     */
    public function toggleChecklist(TaskChecklist $checklist)
    {
        $checklist->toggle(auth()->id());

        return response()->json([
            'success' => true,
            'checklist' => $checklist,
            'progress' => $checklist->task->checklist_progress,
        ]);
    }

    /**
     * Add checklist item
     */
    public function addChecklist(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $checklist = $task->checklists()->create([
            'title' => $request->title,
            'sort_order' => $task->checklists()->count(),
        ]);

        $task->logActivity('checklist_added', null, null, null, "چک‌لیست «{$request->title}» اضافه شد");

        return response()->json([
            'success' => true,
            'checklist' => $checklist,
        ]);
    }

    /**
     * User performance report
     */
    public function userReport(Request $request)
    {
        $users = User::staff()->get();

        $reports = $users->map(function ($user) {
            $stats = Task::getUserStats($user->id);
            $completedThisMonth = Task::forUser($user->id)
                ->byStatus('done')
                ->whereMonth('completed_at', now()->month)
                ->count();

            return [
                'user' => $user,
                'stats' => $stats,
                'completed_this_month' => $completedThisMonth,
                'completion_rate' => $stats['total'] > 0
                    ? round(($stats['completed'] / $stats['total']) * 100)
                    : 0,
            ];
        })->sortByDesc('completion_rate');

        return view('task::reports.users', compact('reports'));
    }

    /**
     * Convert Persian digits to Latin
     */
    protected function persianToLatin(string $string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($persian, $latin, $string);
        $string = str_replace($arabic, $latin, $string);

        return $string;
    }
}
