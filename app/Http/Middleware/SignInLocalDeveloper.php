<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class SignInLocalDeveloper
{
    /**
     * Locally, with AUTO_LOGIN enabled, the landing page signs in a local developer without GitHub.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isLocal() || ! config('app.auto_login')) {
            return $next($request);
        }

        $user = User::query()->firstOrCreate(
            ['github_id' => 'local'],
            ['username' => 'local_dev', 'name' => 'Local Developer'],
        );

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }
}
