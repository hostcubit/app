<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Session;
use Illuminate\Support\Facades\Auth;


class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return View
     */
    public function create(): View {

        $title = translate("User Login");
        return view('user.auth.login', compact('title'));
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param LoginRequest $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse {

        $request->authenticate();
        $request->session()->regenerate();
        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     * @return RedirectResponse
     */
    public function destroy(): RedirectResponse {
        
        $lang = session('lang');
        $flag = session('flag');

        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        session()->put('lang', $lang);
        session()->put('flag', $flag);

        return redirect('/login');
    }

}
