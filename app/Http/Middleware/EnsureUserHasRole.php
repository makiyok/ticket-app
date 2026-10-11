<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

class EnsureUserHasRole
{
    /**
     * 指定したロールのユーザーだけを通す（例: role:organizer,admin）
     *
     * @throws ValueError 存在しないロール名を指定したとき（例: role:organiser）
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(UserRole::from(...), $roles);
        $user = $request->user();

        abort_unless($user !== null && in_array($user->role, $allowedRoles, true), 403);

        return $next($request);
    }
}
