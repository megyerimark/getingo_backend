<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'message' => 'Üdv a Getingo Admin Paneljén!',
            'statistics' => [
                'total_users' => User::count(),
                'students' => User::where('role', 'student')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'banned' => User::where('is_banned', true)->count(),
            ],
        ]);
    }
}
