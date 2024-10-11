<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\RegisterMailJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\PasswordReset;
use App\Models\User;
use App\Http\Utility\SendMail;
use Carbon\Carbon;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class NewPasswordController extends Controller
{
    public function create($token) {

        $passwordToken  = $token;
        $title          = "Password change";
        $email          = session()->get('password_reset_user_email');
        $userResetToken = PasswordReset::where('email', $email)->where('token', $token)->first();
        if(!$userResetToken) {

            $notify[] = ['error', 'Invalid token'];
            return redirect(route('password.request'))->withNotify($notify);
        }
        return view('user.auth.reset',compact('title', 'passwordToken'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function store(Request $request): RedirectResponse {

        $request->validate([

            'password' => 'required|confirmed|min:6',
            'token'    => 'required|exists:password_resets,token',
        ]);
        $email          = session()->get('password_reset_user_email');
        $userResetToken = PasswordReset::where('email', $email)->where('token', $request->input('token'))->first();

        if(!$userResetToken) {

            $notify[] = ['error', 'Invalid token'];
            return redirect(route('password.request'))->withNotify($notify);
        }

        $user           = User::where('email', $email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        if(session()->get('password_reset_user_email')){
            session()->forget('password_reset_user_email');
        }

        $mailCode = [
            'time' => Carbon::now(),
            'name' => site_settings("site_name")
        ];

        RegisterMailJob::dispatch($user, 'PASSWORD_RESET_CONFIRM', $mailCode);
        
        $userResetToken->delete();

        $notify[] = ['success', 'Password changed successfully'];
        return redirect(route('login'))->withNotify($notify);
    }
}
