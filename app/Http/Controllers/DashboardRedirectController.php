<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasRole([
            'Super Admin',
            'Admin',
            'Editor',
            'Support',
        ])) {
            return redirect('/' . config('agency.admin_path', 'manage'));
        }

        if (! $user->hasRole('Client')) {
            $user->assignRole('Client');
        }

        return redirect()->route('client-dashboard.index');
    }
}
