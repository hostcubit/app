<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Models\User;
use App\Service\Admin\Core\CustomerService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public CustomerService $customerService;

    public function __construct(CustomerService $customerService) {

        $this->customerService = $customerService;
    }

    /**
     * Display the registration view.
     * @return View
     */
    public function create(): View {

        $title = "Registration";
        return view('user.auth.register', compact('title'));
    }

    /**
     * @param UserStoreRequest $request
     * @return Application|RedirectResponse|Redirector
     */
    public function store(UserStoreRequest $request): Redirector|RedirectResponse|Application {

        $user = User::create([
            'name'                   => $request->input('name'),
            'email'                  => $request->input('email'),
            'gateway_credentials'   => config('setting.gateway_credentials'),
            'password'               => Hash::make($request->input('password')),
            'email_verified_code'    => randomNumber(),
            'email_verified_send_at' => carbon(),
        ]);

        $this->customerService->applyOnboardingBonus($user);
        $this->customerService->handleEmailVerification($user);
        Auth::login($user);
        
        return redirect()->route('user.dashboard');
    }
}
