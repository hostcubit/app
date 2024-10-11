<?php
namespace App\Http\Controllers\Auth;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Service\Admin\Core\CustomerService;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
class GoogleAuthenticatedController extends Controller
{
    public CustomerService $customerService;
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }
    public function redirectToGoogle()
    {
        if(json_decode(site_settings("social_login_with"), true)["google_oauth"]["status"] == StatusEnum::FALSE->status()) {

            $notify[] = ['error', 'Currently, social login is unavailable'];
            return back()->withNotify($notify);
        }
        return Socialite::driver('google')->redirect();
    }
    /**
     * @return RedirectResponse
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $user = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/');
        }
        $existingUser = User::where('email', $user->email)->first();
        if($existingUser){
            Auth::login($existingUser);
        } else {
            $newUser  = new User();
            $newUser->name = $user->name;
            $newUser->email = $user->email;
            $newUser->google_id = $user->id;
            $newUser->email_verified_status = StatusEnum::TRUE->status();
            $newUser->email_verified_code = null;
            $newUser->email_verified_at = carbon();
            $newUser->save();
            $this->customerService->applyOnboardingBonus($newUser);
            Auth::login($newUser);
        }
        return redirect(RouteServiceProvider::HOME);
    }
}