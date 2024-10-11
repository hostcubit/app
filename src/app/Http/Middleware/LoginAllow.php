<?php

namespace App\Http\Middleware;

use App\Enums\StatusEnum;
use Closure;
use Illuminate\Http\Request;

class LoginAllow
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $member_authentication = json_decode(site_settings("member_authentication"), true);
        
        if (array_key_exists('login', $member_authentication) && $member_authentication['login'] == StatusEnum::FALSE->status()) {

            $notify[] = ['error', translate('Login is currently off')];
            return back()->withNotify($notify);
        }
        return $next($request);
    }
}
