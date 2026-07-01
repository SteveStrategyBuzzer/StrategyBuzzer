<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\GameModeAccessService;
use Illuminate\Support\Facades\Auth;

class RequireModeAccess
{
    public function __construct(private GameModeAccessService $service) {}

    /**
     * Usage in routes:
     *   ->middleware('mode.access:duo,any')   — full OR invite-only access
     *   ->middleware('mode.access:duo,full')  — full access only (creation routes)
     *   ->middleware('mode.access:league,any')
     *   ->middleware('mode.access:league,full')
     */
    public function handle(Request $request, Closure $next, string $mode, string $level = 'any'): mixed
    {
        $user = Auth::user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['error' => 'unauthenticated'], 401)
                : redirect()->route('login');
        }

        // Full access always passes
        if ($this->service->canAccessMode($user, $mode)) {
            return $next($request);
        }

        // Invite-only access: allowed only for 'any' level routes (join / view)
        if ($level === 'any' && $this->service->hasTemporaryInviteAccess($user, $mode)) {
            return $next($request);
        }

        // Locked — redirect to boutique with context
        if ($request->expectsJson()) {
            return response()->json([
                'error'  => 'mode_locked',
                'mode'   => $mode,
                'reason' => 'access_required',
            ], 403);
        }

        $tab = $mode === 'duo' ? 'duo' : 'league';

        return redirect()->route('boutique', ['tab' => $tab])
            ->with('mode_locked', $mode);
    }
}
