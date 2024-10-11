<?php

namespace App\Http\Middleware;

use App\Enums\StatusEnum;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthorizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $authenticatedUser = Auth::user();
        
        if ($authenticatedUser->status != StatusEnum::TRUE->status() || ($authenticatedUser->email_verified_status != StatusEnum::TRUE->status() && site_settings("registration_otp_verification") == StatusEnum::TRUE->status() && site_settings("email_otp_verification") == StatusEnum::TRUE->status())) {
            
            return redirect()->route('user.authorization.process');
        }

        return $next($request);
    }
}
