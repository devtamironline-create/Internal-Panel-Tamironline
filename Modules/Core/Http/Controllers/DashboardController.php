<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function admin()
    {
        // Calculate statistics
        $stats = [
            'staff_count' => User::staff()->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
