<?php

namespace App\Http\Controllers;

use App\Enums\StatusEnum;
use App\Http\Utility\SendMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthorizationProcessController extends Controller
{
    public function process()
    {
        $user = Auth::user();
       
        if (site_settings("registration_otp_verification") == StatusEnum::TRUE->status() && site_settings("email_otp_verification") == StatusEnum::TRUE->status() && $user->email_verified_status == StatusEnum::FALSE->status()) {
            
            $setTitle = 'Verification form for email';
            return view('user.auth.email', compact('user', 'setTitle'));
        }
        
        return redirect()->route('user.dashboard');
    }


    public function checkAuthorizationValidationCode(User $user, $code): bool
    {
        if (Carbon::parse($user->email_verified_send_at)->addMinute()->isPast()  || $user->email_verified_code !== $code) {
            return false;
        }

        return true;
    }


    public function processEmailVerification()
    {

        $user = Auth::user();

        if ($this->checkAuthorizationValidationCode($user, $user->email_verified_code)) {
            $notify[] = ['error', 'Verification message code not received. Please check your inbox and spam folder. If you haven\'t received the code after 1 minute, please request a new code'];
            return back()->withNotify($notify);
        }

        $user->email_verified_code = randomNumber();
        $user->email_verified_send_at = carbon();
        $user->save();

        $mailCode = [
            'name' => site_settings("site_name"),
            'code' => $user->email_verified_code,
            'time' => carbon(),
        ];

        SendMail::MailNotification($user, 'REGISTRATION_VERIFY', $mailCode);

        $notify[] = ['sucess', translate('Email Verification code Send')];
        return back()->withNotify($notify);
    }

    public function emailVerification(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:2|max:10',
        ]);

        $user = Auth::user();
        if ($user->email_verified_code !== $request->input('code')) {
            $notify[] = ['error', 'Verification code did not match'];
            return back()->withNotify($notify);
        }
        $user->email_verified_status = StatusEnum::TRUE->status();
        $user->email_verified_code = null;
        $user->email_verified_at = carbon();
        $user->save();

        return redirect()->route('user.dashboard');
    }

}
