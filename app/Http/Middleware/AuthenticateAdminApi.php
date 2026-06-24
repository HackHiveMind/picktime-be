<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdminApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        if (Auth::check()) {
            $this->recordAuthDuration($request, $startedAt);

            return $next($request);
        }

        $token = $request->bearerToken();

        if (filled($token)) {
            $user = User::query()
                ->where('admin_api_token_hash', hash('sha256', $token))
                ->where(function ($query): void {
                    $query
                        ->whereNull('admin_api_token_expires_at')
                        ->orWhere('admin_api_token_expires_at', '>', now());
                })
                ->first();

            if ($user) {
                Auth::setUser($user);
                $this->recordAuthDuration($request, $startedAt);

                return $next($request);
            }
        }

        $this->recordAuthDuration($request, $startedAt);

        abort(401, 'Unauthenticated.');
    }

    private function recordAuthDuration(Request $request, float $startedAt): void
    {
        $request->attributes->set('timing.admin_auth', microtime(true) - $startedAt);
    }
}
