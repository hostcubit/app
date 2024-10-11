<?php

namespace App\Http\Controllers\User;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\MailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Gateway;
use App\Models\PricingPlan;

class EmailApiGatewayController extends Controller
{
    public function index() {

        $user             = Auth::user();
        $allowed_access   = planAccess($user);
        if($allowed_access) {
            $allowed_access   = (object)planAccess($user);
        } else {
            $notify[] = ['error','Please Purchase A Plan'];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        $gateways         = $allowed_access->type == StatusEnum::FALSE->status() ?
                            Gateway::where('user_id', $user->id)->whereNotNull('mail_gateways')->orderBy('is_default', 'DESC')->paginate(paginateNumber(site_settings("paginate_number"))) 
                            : Gateway::whereNull('user_id')->whereNotNull('mail_gateways')->orderBy('is_default', 'DESC')->paginate(paginateNumber(site_settings("paginate_number")));

        $gatewaysForCount = $allowed_access->type == StatusEnum::FALSE->status() ? 
                            Gateway::where('user_id', $user->id)->whereNotNull('mail_gateways')->where('status',1)->get()
                            : Gateway::whereNull('user_id')->whereNotNull('mail_gateways')->where('status',1)->get();  
        $gatewayCount     = $gatewaysForCount->groupBy('type')->map->count(); 
        
        $title            = "Mail Configuration";
        $credentials      = config('setting.gateway_credentials.email');

        return view('user.mail.index', compact('title', 'gateways', 'credentials', 'user', 'gatewayCount', 'allowed_access'));
    }


    /**
    * Create Gateway
    * @param Request $request
    */
    public function create(Request $request) {
        
        $user = Auth::user();
        $this->validate($request, [
            'type'               => "required",
            'driver_information' => ["required"],
            'name'               => ["required"],
            'status'             => "required|in:0,1",
        ]);
  
        $mail                     = new Gateway();
        $mail->user_id            = $user->id;
        $mail->status             = $request->input('status');
        $mail->type               = $request->input('type');
        $mail->name               = $request->input('name');
        $mail->address            = $request->input('address');
        $mail->mail_gateways       = $request->input('driver_information');
        $mail->sms_gateways      = null;
        $mail->save();

        $notify[] = ['success', 'A new '.ucfirst($mail->type). ' method has been created under: '.ucfirst($mail->name)];
        return back()->withNotify($notify);
    }


     /**
     * Updates the gateway  
     * @param Request $request
     * 
     */
    public function update(Request $request) {

        $user = Auth::user();
        $plan = $user->runningSubscription()->currentPlan()->email->allowed_gateways;

        $gateways     = Gateway::where('user_id', $user->id)->whereNotNull('mail_gateways')->where('status',1)->get();
        $gatewayCount = $gateways->groupBy('type')->map->count(); 

        $this->validate($request, [
            'type'               => "required",
            'driver_information' => "required",
            'name'               => ["required", "unique:gateways,name,".request()->id],
            'address'            => "required",
            'status'             => "required|in:0,1",
        ]); 
        
        if($gatewayCount->sum() < collect($plan)->sum() || $request->input('status') == 1) {

            if(array_key_exists($request->type, (array)$plan)) { 

                $mail = Gateway::findOrFail($request->id);
                $mail->type               = $request->input('type');
                $mail->name               = $request->input('name');
                $mail->address            = $request->input('address');
                $mail->mail_gateways      = $request->input('driver_information');
                $mail->status             = $request->input('status');
                $mail->save();
    
                $notify[] = ['success', ucfirst($mail->type). ' method under: '.ucfirst($mail->name). ' has been updated'];
                return back()->withNotify($notify);
            }
            else{
                $notify[] = ['error', "You Do Not Have The Permission To Update ". strtoupper($request->input('type')) ." Gateway!"];
                return back()->withNotify($notify);
            }
        }
        else{
            $notify[] = ['error', "Your Current Plan Only Allows You To Edit ".  collect($plan)->sum() ." active gateway!"];
            return back()->withNotify($notify);
        }
        
        
    }


    /**
     * Updates the default gateway status 
     * @param Request $request
     * @return mixed
     */
    public function defaultStatus(Request $request) :mixed {

        $user = Auth::user();
        $gateway = Gateway::findOrFail($request->id);

        if($gateway->status == 1) {

            $general             = GeneralSetting::first();
            $general->mail_from  = $gateway->address;
            $general->save();
            Gateway::whereNotNull(['mail_gateways', 'user_id'])->where('user_id', $user->id)->update(["is_default" => 0 ]);
            $gateway->is_default =  $request->default_value;
            $gateway->update();
            return json_encode([
                'reload' => true,
                'status' => true,
            ]);
        }
        else {

            return json_encode([
                'reload' => true,
                'status' => false,
            ]);
        }
    }

    /**
    * Delete Gateway
    * @param Request $request
    */
    public function delete(Request $request) {

        $gateway  = Gateway::find($request->id);
        $gateway->delete();
        $notify[] = ['success', 'Gateway has been successfully deleted'];
        return back()->withNotify($notify);
    }
}
