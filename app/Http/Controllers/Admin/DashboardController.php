<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request) {
         $user = auth()->user()->load('roles');

        $roles =  $user->roles->pluck('name')->toArray();

        $request->session()->put('role', $roles);

        return Inertia::render('Dashboard');
    }
}
