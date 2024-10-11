<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\SmsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\Gateway;
use Illuminate\Support\Facades\Auth;

class SmsApiGatewayController extends Controller
{
    /**
    * Create Gateway
    * @param Request $request
    */
    public function create(Request $request) {

        $user = Auth::user();
        $plan = $user->runningSubscription()->currentPlan()->sms->allowed_gateways;
        $gateways     = Gateway::where('user_id', $user->id)->whereNotNull('sms_gateways')->where('status',1)->get();
        $gatewayCount = $gateways->groupBy('type')->map->count(); 

        $this->validate($request, [
            'type'               => "required",
            'driver_information' => ["required"],
            'name'               => ["required"],
            'status'             => "required|in:0,1",
        ]);
        if($gatewayCount->sum() < collect($plan)->sum() || $request->input('status') == 0) {
            $filterType = preg_replace(array('/[[:digit:]]/'),'', $request->type);
           
            if(array_key_exists($filterType, (array)$plan)) { 
               
                $sms                     = new Gateway();
                $sms->user_id            = $user->id;
                $sms->status             = $request->input('status');
                $sms->type               = $request->input('type');
                $sms->name               = $request->input('name');
                $sms->sms_gateways       = $request->input('driver_information');
                $sms->mail_gateways      = null;
                $sms->save();

                $notify[] = ['success', 'A new '.ucfirst($filterType). ' method has been created under: '.ucfirst($sms->name)];
                return back()->withNotify($notify);
            }
            else{
                $notify[] = ['error', "You Do Not Have The Permission To Create ". $filterType ." Gateway!"];
                return back()->withNotify($notify);
            }
        }
        else{
            $notify[] = ['error', "Your Current Plan Only Allows You To Keep ".  collect($plan)->sum() ." Gateways active!"];
            return back()->withNotify($notify);
        }
    }

    public function defaultGateway(Request $request)
    {
        $this->validate($request, [
           'default_gateway_id' => 'required|exists:sms_gateways,id'
        ]);

        $user = Auth::user();
        $credentials = $user->gateway_credentials;
        Arr::set($credentials, 'sms.default_gateway_id', $request->input('default_gateway_id'));

        $user->gateway_credentials = $credentials;
        $user->save();

        $notify[] = ['success', 'Default SMS Gateway has been updated'];
        return back()->withNotify($notify);
    }

    

    public function update(Request $request)
    {
        $user = Auth::user();
        $plan = $user->runningSubscription()->currentPlan()->sms->allowed_gateways;

        $gateways     = Gateway::where('user_id', $user->id)->whereNotNull('mail_gateways')->where('status',1)->get();
        $gatewayCount = $gateways->groupBy('type')->map->count(); 
        $this->validate($request, [
            'type'               => "required",
            'driver_information' => "required",
            'name'               => ["required"],
            'status'             => "required|in:0,1",
        ]); 
 
        if($gatewayCount->sum() < collect($plan)->sum() || $request->input('status') == 0) {
            $filterType = preg_replace(array('/[[:digit:]]/','/_/','/ /'),'', setInputLabel($request->type));
            if(array_key_exists($filterType, (array)$plan)) { 
                
            $sms = Gateway::findOrFail($request->id);
            $sms->type               = $request->input('type');
            $sms->name               = $request->input('name');
            $sms->address            = $request->input('address');
            $sms->sms_gateways       = $request->input('driver_information');
            $sms->status             = $request->input('status');
            $sms->save();

            $notify[] = ['success', ucfirst($filterType). ' method under: '.ucfirst($sms->name). ' has been updated'];
            return back()->withNotify($notify);
            }
            else{
                $notify[] = ['error', "You Do Not Have The Permission To Update ". $filterType ." Gateway!"];
                return back()->withNotify($notify);
            }
        }
        else{
            $notify[] = ['error', "Your Current Plan Only Allows You To Keep ".  collect($plan)->sum() ." Gateways active!"];
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
            Gateway::whereNotNull(['sms_gateways', 'user_id'])->where('user_id', $user->id)->update(["is_default" => 0 ]);
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
