<?php

namespace Modules\Task\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Task\Models\Team;

class TeamController extends Controller
{
    /**
     * Display a listing of teams.
     */
    public function index()
    {
        $teams = Team::withCount('members', 'tasks')->orderBy('sort_order')->get();

        return view('task::teams.index', compact('teams'));
    }

    /**
     * Show the form for creating a new team.
     */
    public function create()
    {
        $users = User::where('is_active', true)->orderBy('first_name')->get();
        $icons = $this->getAvailableIcons();
        $colors = $this->getAvailableColors();

        return view('task::teams.create', compact('users', 'icons', 'colors'));
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|max:50',
            'icon' => 'required|string|max:50',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'],
            'icon' => $validated['icon'],
            'is_active' => true,
            'sort_order' => Team::max('sort_order') + 1,
        ]);

        if (!empty($validated['members'])) {
            $team->members()->attach($validated['members']);
        }

        // ساخت گروه چت اختصاصی تیم
        $this->createTeamConversation($team);

        return redirect()->route('teams.index')
            ->with('success', 'تیم با موفقیت ایجاد شد.');
    }

    /**
     * Show the form for editing a team.
     */
    public function edit(Team $team)
    {
        $users = User::where('is_active', true)->orderBy('first_name')->get();
        $icons = $this->getAvailableIcons();
        $colors = $this->getAvailableColors();
        $teamMemberIds = $team->members->pluck('id')->toArray();

        return view('task::teams.edit', compact('team', 'users', 'icons', 'colors', 'teamMemberIds'));
    }

    /**
     * Update the specified team.
     */
    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|max:50',
            'icon' => 'required|string|max:50',
            'is_active' => 'boolean',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ]);

        $team->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'],
            'icon' => $validated['icon'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Sync team members
        $team->members()->sync($validated['members'] ?? []);

        // سینک اعضای گروه چت تیم
        $this->syncTeamConversationMembers($team);

        return redirect()->route('teams.index')
            ->with('success', 'تیم با موفقیت بروزرسانی شد.');
    }

    /**
     * Remove the specified team.
     */
    public function destroy(Team $team)
    {
        // Check if team has tasks
        if ($team->tasks()->count() > 0) {
            return back()->with('error', 'این تیم دارای تسک است و قابل حذف نیست.');
        }

        $team->members()->detach();
        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', 'تیم با موفقیت حذف شد.');
    }

    /**
     * Update team sort order (AJAX).
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'teams' => 'required|array',
            'teams.*' => 'exists:teams,id',
        ]);

        foreach ($validated['teams'] as $index => $teamId) {
            Team::where('id', $teamId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Toggle team active status (AJAX).
     */
    public function toggleStatus(Team $team)
    {
        $team->update(['is_active' => !$team->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $team->is_active,
        ]);
    }

    /**
     * Get available icons for teams.
     */
    private function getAvailableIcons(): array
    {
        return [
            'cog' => 'تنظیمات (چرخ‌دنده)',
            'phone' => 'تلفن',
            'currency-dollar' => 'مالی (دلار)',
            'users' => 'کاربران',
            'code' => 'کدنویسی',
            'chart-bar' => 'نمودار',
            'document' => 'سند',
            'shopping-cart' => 'فروش',
            'truck' => 'حمل‌ونقل',
            'wrench' => 'تعمیرات',
            'cube' => 'انبار',
            'briefcase' => 'مدیریت',
        ];
    }

    /**
     * Get available colors for teams.
     */
    private function getAvailableColors(): array
    {
        return [
            'blue' => 'آبی',
            'green' => 'سبز',
            'yellow' => 'زرد',
            'red' => 'قرمز',
            'purple' => 'بنفش',
            'pink' => 'صورتی',
            'indigo' => 'نیلی',
            'teal' => 'فیروزه‌ای',
            'orange' => 'نارنجی',
            'gray' => 'خاکستری',
        ];
    }

    /**
     * ساخت گروه چت اختصاصی برای تیم
     */
    private function createTeamConversation(Team $team): void
    {
        if ($team->conversation) {
            return;
        }

        $conversation = \App\Models\Chat\Conversation::create([
            'type' => 'group',
            'name' => 'چت تیم ' . $team->name,
            'description' => 'گروه چت اختصاصی تیم ' . $team->name,
            'created_by' => auth()->id(),
            'team_id' => $team->id,
            'settings' => [
                'is_public' => false,
                'members_can_add_others' => false,
            ],
        ]);

        // اضافه کردن اعضای تیم به گروه چت
        $memberIds = $team->members()->pluck('users.id')->toArray();
        if (auth()->id() && !in_array(auth()->id(), $memberIds)) {
            $memberIds[] = auth()->id();
        }

        foreach ($memberIds as $userId) {
            $conversation->participants()->attach($userId, [
                'joined_at' => now(),
                'is_admin' => $userId === auth()->id(),
            ]);
        }
    }

    /**
     * سینک اعضای گروه چت با اعضای تیم
     */
    private function syncTeamConversationMembers(Team $team): void
    {
        $conversation = $team->conversation;
        if (!$conversation) {
            $this->createTeamConversation($team);
            return;
        }

        $teamMemberIds = $team->members()->pluck('users.id')->toArray();
        $currentParticipantIds = $conversation->activeParticipants()->pluck('users.id')->toArray();

        // اضافه کردن اعضای جدید
        $toAdd = array_diff($teamMemberIds, $currentParticipantIds);
        foreach ($toAdd as $userId) {
            if ($conversation->participants()->where('user_id', $userId)->exists()) {
                $conversation->participants()->updateExistingPivot($userId, ['left_at' => null, 'joined_at' => now()]);
            } else {
                $conversation->participants()->attach($userId, ['joined_at' => now()]);
            }
        }

        // حذف اعضایی که از تیم خارج شدن (soft: فقط left_at ست میشه)
        $toRemove = array_diff($currentParticipantIds, $teamMemberIds);
        // سازنده گروه رو حذف نکن
        $creatorId = $conversation->created_by;
        foreach ($toRemove as $userId) {
            if ($userId === $creatorId) continue;
            $conversation->participants()->updateExistingPivot($userId, ['left_at' => now()]);
        }
    }
}
