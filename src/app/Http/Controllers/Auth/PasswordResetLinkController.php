<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;
use App\Http\Utility\SendMail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     * @return View
     */
    public function create(): View {

        $title = "forgot password";
        return view('user.auth.forgot-password', compact('title'));
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */

    public function store(Request $request) {

        $request->validate([

            'email' => ['required', 'email'],
        ]);
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {

            $notify[] = ['error', 'User not found.'];
            return back()->withNotify($notify);
        }

        PasswordReset::where('email', $request->input('email'))->delete();

        $passwordReset = PasswordReset::create([
            'email'      => $request->input('email'),
            'token'      => randomNumber(),
            'created_at' => Carbon::now(),
        ]);

        $mailCode = [
            'code' => $passwordReset->token,
            'time' => $passwordReset->created_at,
        ];

        SendMail::MailNotification($user,'PASSWORD_RESET',$mailCode);
        session()->put('password_reset_user_email', $request->input('email'));
        $notify[] = ['success', 'check your email password reset code sent successfully'];
        return redirect(route('password.verify.code'))->withNotify($notify);
    }


    public function passwordResetCodeVerify() {

        $title = 'Password Reset';
        $route = "password.email.verify.code";

        if(!session()->get('password_reset_user_email')) {
            $notify[] = ['error','Your email session expired please try again'];
            return redirect()->route('password.request')->withNotify($notify);
        }

        return view('user.auth.verify_code',compact('title','route'));
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function emailVerificationCode(Request $request): mixed {

        $this->validate($request, [

            'code' => 'required'
        ]);
        $code           = preg_replace('/[ ,]+/', '', trim($request->code));
        $email          = session()->get('password_reset_user_email');
        $userResetToken = PasswordReset::where('email', $email)->where('token', $code)->first();

        if(!$userResetToken) {

            $notify[] = ['error', 'Invalid token'];
            return redirect(route('password.request'))->withNotify($notify);
        }
        $notify[] = ['success', 'Change your password.'];
        return redirect()->route('password.reset', $code)->withNotify($notify);
    }

    public function resendCode() {

        $email = session()->get('password_reset_user_email');
        $user  = User::where('email',$email)->first();
        $reset = PasswordReset::where('email', $email)->first();

        if(!$user || !$reset) {

            $notify[] = ['error','Your email session expired please try again'];
            return redirect()->route('password.request')->withNotify($notify);
        }

        if (!Carbon::parse($reset->created_at)->addMinute()->isPast()) {
            
            $notify[] = ['error', 'Verification message code not received. Please check your inbox and spam folder. If you haven\'t received the code after 1 minute, please request a new code'];
            return back()->withNotify($notify);
        }

        $reset->delete();

        $passwordReset = PasswordReset::create([
            'email'      => $user->email,
            'token'      => randomNumber(),
            'created_at' => Carbon::now(),
        ]);

        $mailCode = [
            'code' => $passwordReset->token,
            'time' => $passwordReset->created_at,
        ];

        SendMail::MailNotification($user,'PASSWORD_RESET',$mailCode);
        $notify[] = ['success','Email Confirmation Code Dispatched'];
        return back()->withNotify($notify);
    }
}
