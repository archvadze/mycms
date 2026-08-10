<?php
namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        $user->unsetRelation('roles');

        if (! AdminAccess::canAccessPanel($user)) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
