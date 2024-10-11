<?php

namespace App\Http\Controllers\Admin\Core;

use App\Models\User;
use App\Enums\StatusEnum;
use Illuminate\View\View;
use App\Models\AndroidApi;
use App\Enums\ServiceType;
use App\Models\PricingPlan;
use Illuminate\Support\Arr;
use App\Models\WhatsappDevice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UserStoreRequest;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\UserCreditRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Service\Admin\Core\CustomerService;

class CustomerController extends Controller
{
    public CustomerService $customerService;

    public function __construct(CustomerService $customerService) {

        $this->customerService = $customerService;
    }

    /**
     * @return \Illuminate\View\View
     * 
     */
    public function index(): View {

        Session::put("menu_active", true);
        $title     = translate("All Members");
        $customers = $this->customerService->getPaginateUsers();
        return view('admin.customer.index', compact('title', 'customers'));
    }

    /**
     * @param int $id
     * 
     * @return \Illuminate\View\View
     * 
     */
    public function details(int $id): View {
        
        Session::put("menu_active", true);

        $title         = translate("User Details");
        $user          = $this->customerService->findById($id);
        $logs          = $this->customerService->logs($user->id);
        $pricing_plans = PricingPlan::where("status", StatusEnum::TRUE->status())->pluck("name", "id")->toArray();
        
        return view('admin.customer.details', compact('title', 'user', 'logs', "pricing_plans"));
    }

    /**
     * @param UserStoreRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function store(UserStoreRequest $request) {

        $notify[] = ['error', 'Something went wrong'];
        
        try {

            $user = User::create([
                'name'                  => $request->input('name'),
                'email'                 => $request->input('email'),
                'status'                => StatusEnum::TRUE->status(),
                'password'              => Hash::make($request->input('password')),
                'email_verified_code'   => null,
                'email_verified_at'     => carbon(),
                'email_verified_status' => StatusEnum::TRUE->status(),
            ]);
            $notify = $this->customerService->applyOnboardingBonus($user);

        } catch(\Exception $e) {

            $notify = ['error', $e->getMessage()];
        }
        return back()->withNotify($notify);
    }

    /**
     * @param UserUpdateRequest $request
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function update(UserUpdateRequest $request, int $id): mixed {

        $status  = 'error';
        $message = 'Something went wrong';
        try {

            $user = $this->customerService->findById($id);
           
            if($request->filled('pricing_plan')) {

                $this->customerService->updatePlan($user, $request);
            }

            AndroidApi::where([

                "user_id" => $user->id, 
                "status"  => 1
            ])->update(["status" => 2]);

            WhatsappDevice::where([

                "user_id" => $user->id, 
                "status"  => "connected"
            ])->update(["status" => "disconnected"]);

            $user->fill($this->userData($request->validated()));
            $user->address = [
                
                'address' => $request->input('address'),
                'city'    => $request->input('city'),
                'state'   => $request->input('state'),
                'zip'     => $request->input('zip')
            ];
            $user->save();
            $status  = 'success';
            $message = translate("User updated successfully");
        } catch (\Exception $e) {

            $status  = 'error';
            $message = $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }


    private function userData($data) {

        if(array_key_exists('city', $data)) {

            unset($data['city']);
        }
        if(array_key_exists('state', $data)) {

            unset($data['state']);
        }
        if(array_key_exists('zip', $data)) {

            unset($data['zip']);
        }
        return $data;
    }
    /**
     * @param UserCreditRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function modifyCredit(UserCreditRequest $request): mixed {

        $status  = 'error';
        $message = translate("Something went wrong");
        try {

            $user = $this->customerService->findByUid($request->input('uid'));
            if(!$user) {

                $notify[] = ['error', translate('User not found')];
                return back()->withNotify($notify);
            }
            $credits = $this->customerService->buildCreditArray($request);
            foreach ($credits as $type => $credit) {
               
                $column = $type . '_credit';
                
                if ($credit > 0) {
                    
                    if ($request->input('type') == StatusEnum::FALSE->status() && $user->$column < $credit) {

                        $notify[] = ['error', translate('Invalid ' . ucfirst($type) . ' Credit Amount')];
                        return back()->withNotify($notify);
                    }
                }
                $status = 'success';
                if ($request->input('type') == StatusEnum::TRUE->status()) { 
                    
                    $message = translate("Credit has been added by Admin");
                    $this->customerService->addedCreditLog($user, $request->input($type.'_credit'), constant(ServiceType::class . '::' . strtoupper($type))->value, true, $message);
                    
                } else {
                   
                    $message = translate("Credit has been deducted by Admin");
                    $this->customerService->deductCreditLog($user, $request->input($type.'_credit'), constant(ServiceType::class . '::' . strtoupper($type))->value, true, $message);
                }
            }
            $user->save();

        } catch (\Exception $e) {
            
            $message = $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     * @param int $uid
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function login(string $uid) {

        $user = User::where('uid',$uid)->first();
        Auth::guard('web')->loginUsingId($user->id);
        $notify[] = ['success', "Logged in as $user->name"];
        return redirect()->route('user.dashboard')->withNotify($notify);
    }
}