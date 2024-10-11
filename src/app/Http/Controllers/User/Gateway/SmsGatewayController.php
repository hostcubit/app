<?php

namespace App\Http\Controllers\User\Gateway;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SmsGatewayRequest;
use Illuminate\Http\Request;
use App\Models\SmsGateway;
use App\Models\Gateway;
use App\Service\Admin\Gateway\SmsGatewayService;
use App\Traits\ModelAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class SmsGatewayController extends Controller
{
    use ModelAction;
    public $smsGatewayService;
    public function __construct() {

        $this->smsGatewayService = new SmsGatewayService();
    }

    /**
     * 
     * @return \Illuminate\View\View
     * 
     */
    public function index() {

        Session::put("menu_active", false);
    	$title        = translate("SMS API Gateway list");
        $user = auth()->user();
        $gatewaysForCount = Gateway::where('user_id', $user->id)->whereNotNull('sms_gateways')->get();
    	$credentials = SmsGateway::orderBy('id','asc')->get();
        $allowed_access = (object)planAccess($user);
        if($allowed_access) {
            $allowed_access   = (object)planAccess($user);
        } else {
            $notify[] = ['error', translate('Please Purchase A Plan')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }
        $gatewayCount = $gatewaysForCount->groupBy('type')->map->count();
        
    	$sms_gateways = $allowed_access->type == StatusEnum::FALSE->status() ? 
                            Gateway::where('user_id', auth()->user()->id)
                                        ->whereNotNull('sms_gateways')
                                        ->orderBy('is_default', 'DESC')
                                        ->search(['name'])
                                        ->filter(['status'])
                                        ->date()
                                        ->paginate(paginateNumber(site_settings("paginate_number"))) :
                            Gateway::whereNull('user_id')
                                        ->whereNotNull('sms_gateways')
                                        ->orderBy('is_default', 'DESC')
                                        ->search(['name'])
                                        ->filter(['status'])
                                        ->date()
                                        ->paginate(paginateNumber(site_settings("paginate_number")));
                            
        
    	return view('user.gateway.sms.api.index', compact('title', 'sms_gateways', 'credentials', 'allowed_access', 'user', 'gatewayCount'));
    }

    /**
     *
     * @param SmsGatewayRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function store(SmsGatewayRequest $request) {

        $status  = 'error';
        $message = translate("Something went wrong");
        try {

            $user = auth()->user();
            $plan = $user->runningSubscription()->currentPlan()->sms->allowed_gateways;
            $gateways     = Gateway::where('user_id', $user->id)->whereNotNull('sms_gateways')->where('status',1)->get();
            $gatewayCount = $gateways->groupBy('type')->map->count(); 
            if($gatewayCount->sum() < collect($plan)->sum() || $request->input('status') == 0) {
                $filterType = preg_replace(array('/[[:digit:]]/'),'', $request->type);
               
                if(array_key_exists($filterType, (array)$plan)) { 
                   
                    $gateway = $this->smsGatewayService->save($request, auth()->user()->id);
                    $status  = 'success';
                    $message = 'A new '.ucfirst($gateway->type). ' gateway has been created under: '.ucfirst($gateway->name);
                } else {

                    $status = "error";
                    $message = translate("You Do Not Have The Permission To Create ").$filterType.translate(" Gateway!");
                }
            } else {

                $status = "error";
                $message = translate("Your Current Plan Only Allows You To Keep "). collect($plan)->sum() .translate("  Gateways active!");
            }
            

        } catch (\Exception $e) {

            $message = translate("Server Error: ") . $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     *
     * @param SmsGatewayRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function update(SmsGatewayRequest $request) {

        $status  = 'error';
        $message = translate("Something went wrong");
        try {

            $user = auth()->user();
            $plan = $user->runningSubscription()->currentPlan()->sms->allowed_gateways;
            $gateways     = Gateway::where('user_id', $user->id)->whereNotNull('sms_gateways')->where('status',1)->get();
            $gatewayCount = $gateways->groupBy('type')->map->count(); 
            if($gatewayCount->sum() < collect($plan)->sum() || $request->input('status') == 0) {
                $filterType = preg_replace(array('/[[:digit:]]/'),'', $request->type);
               
                if(array_key_exists($filterType, (array)$plan)) { 
                   
                    $gateway = $this->smsGatewayService->save($request, auth()->user()->id);
                    $status  = 'success';
                    $message = ucfirst($gateway->type). ' gateway under: '.ucfirst($gateway->name). ' has been updated';
                } else {

                    $status = "error";
                    $message = translate("You Do Not Have The Permission To Create ").$filterType.translate(" Gateway!");
                }
            } else {

                $status = "error";
                $message = translate("Your Current Plan Only Allows You To Keep "). collect($plan)->sum() .translate("  Gateways active!");
            }

        } catch (\Exception $e) {

            $message = translate("Server Error: ") . $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     * 
     * @param  \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws ValidationException If the validation fails.
     * 
     */
    public function statusUpdate(Request $request) {
        
        try {

            $this->validate($request,[

                'id'     => 'required',
                'value'  => 'required',
                'column' => 'required',
            ]); 
            $user = auth()->user();
            $notify = $this->smsGatewayService->statusUpdate($request, $user->id);
            return $notify;

        } catch (ValidationException $validation) {

            return json_encode([
                
                'status'  => false,
                'message' => $validation->errors()
            ]);
        } 
    }

    /**
     * 
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request) {
       
        $status  = 'success';
        $message = translate('Gateway has been successfully deleted');
        try {
            
            $gateway = Gateway::find($request->id);
            $gateway->delete();
            
        } catch(\Exception $e) {

            $status  = 'success';
            $message = $e->getMessage();
        }
      
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     *
     * @param Request $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function bulk(Request $request) :RedirectResponse {

        $status  = 'success';
        $message = translate("Successfully Performed bulk action");
        try {

            list($status, $message) = $this->bulkAction($request, 'is_default',[
                "model" => new Gateway(),
            ]);
    
        } catch (\Exception $exception) {

            $status  = 'error';
            $message = translate("Server Error: ").$exception->getMessage();
        }

        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }
}
