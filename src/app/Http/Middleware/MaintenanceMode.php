<?php

namespace App\Http\Middleware;

use App\Enums\StatusEnum;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MaintenanceMode
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
        if (site_settings("maintenance_mode") == StatusEnum::TRUE->status()) {
            $site_name = site_settings("site_name");
            $maintenance_mode_message = site_settings("maintenance_mode_message");
            return new Response(view('errors.maintenance',compact('site_name','maintenance_mode_message')));
        }
        return $next($request);
    }
}
