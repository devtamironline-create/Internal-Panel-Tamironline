<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index(Request $request)
    {
        $query = Server::withCount('services');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('hostname', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%");
        }

        $servers = $query->latest()->paginate(20);

        return view('admin.servers.index', compact('servers'));
    }

    public function create()
    {
        return view('admin.servers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hostname' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ip',
            'type' => 'required|in:shared,vps,dedicated,reseller',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Server::create($validated);

        return redirect()->route('admin.servers.index')
            ->with('success', 'سرور جدید ایجاد شد');
    }

    public function edit(Server $server)
    {
        return view('admin.servers.edit', compact('server'));
    }

    public function update(Request $request, Server $server)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hostname' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ip',
            'type' => 'required|in:shared,vps,dedicated,reseller',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $server->update($validated);

        return redirect()->route('admin.servers.index')
            ->with('success', 'سرور بروزرسانی شد');
    }

    public function destroy(Server $server)
    {
        if ($server->services()->count() > 0) {
            return back()->with('error', 'این سرور دارای سرویس فعال است و قابل حذف نیست');
        }

        $server->delete();

        return redirect()->route('admin.servers.index')
            ->with('success', 'سرور حذف شد');
    }
}
