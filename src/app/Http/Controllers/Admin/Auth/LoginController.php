<?php

namespace App\Http\Controllers\Admin\Auth;

use Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class LoginController extends Controller
{
    public function __construct() {

        $this->middleware('admin.guest')->except('logout');
    }

    public function showLogin() {

        $title = translate("Admin Login");
        return view('admin.auth.login', compact('title'));
    }

    public function authenticate(Request $request) {

        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);
        if (Auth::guard('admin')->attempt($credentials)) {

            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request) { 

        $lang = Session::get('lang');
        $flag = Session::get('flag');
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken(); 
        return $this->loggedOut($request, $lang, $flag) ?: redirect('/admin');
    }

    protected function loggedOut(Request $request, $lang, $flag) {
        
        Session::put('lang',$lang);
        Session::put('flag',$flag);
    }
}
