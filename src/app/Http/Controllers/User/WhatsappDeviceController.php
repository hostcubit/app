<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WhatsappDevice;
use App\Models\WhatsappTemplate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use App\Rules\DifferenceRule;
use Exception;
use Illuminate\Support\Facades\Auth;

class WhatsappDeviceController extends Controller
{
    /**
     * create form show
     */
    public function create() {

        $user               = Auth::user();
        $title              = translate("Setup Whatsapp");
        $credentials        = config('setting.whatsapp_business_credentials');
        $whatsappBusinesses = WhatsappDevice::where('user_id', auth()->user()->id)
            ->where("type", WhatsappDevice::BUSINESS)
            ->orderBy('id', 'desc')
            ->paginate(paginateNumber(site_settings("paginate_number")));

        $whatsappNodes      = WhatsappDevice::where('user_id', auth()->user()->id)
            ->where("type", WhatsappDevice::NODE)
            ->orderBy('id', 'desc')
            ->paginate(paginateNumber(site_settings("paginate_number")));

        try {

            $response = Http::get(env('WP_SERVER_URL'));
        } catch (ConnectionException) {

            return view('user.whatsapp_device.error', [
                'title'              => $title,
                'message'            => "Failed to establish a connection with the WhatsApp node server. Please reach out to your service provider for further assistance.",
                'credentials'        => $credentials,
                'whatsappBusinesses' => $whatsappBusinesses,
                'user'               => $user,
            ]);
        }

        foreach ($whatsappNodes as $key => $value) {

            try {

                $findWhatsappsession = Http::get(env('WP_SERVER_URL') . '/sessions/status/' . $value->name);
                $wpu = WhatsappDevice::where('id', $value->id)->first();
                if ($findWhatsappsession->status() == 200) {

                    $wpu->status = 'connected';
                } else {
                    
                    $wpu->status = 'disconnected';
                }
                $wpu->save();
            } catch (\Exception $e) {
                
            }
        }
        return view('user.whatsapp_device.create', [
            'title'              => $title,
            'credentials'        => $credentials,
            'whatsappBusinesses' => $whatsappBusinesses,
            'whatsappNodes'      => $whatsappNodes,
            'user'               => $user,
        ]);
    }

    /**
     * whatsapp store method
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request, $type = null) {

        
        $access = auth()->user()->runningSubscription()->currentPlan()->whatsapp;
        $whatsapp_device_count = WhatsappDevice::where("user_id", auth()->user()->id)->get()->count();
        
        if($request->has("whatsapp_business_api") && $request->input("whatsapp_business_api") == true && $type == "cloud_api") {

            $required_credentials = config("setting.whatsapp_business_credentials.required");

            foreach($required_credentials as $required_cred_key => $required_cred_value) {

                $validate['credentials'][$required_cred_key] = 'required';
            }

            $whatsapp              = new WhatsappDevice();
            $whatsapp->user_id     = auth()->user()->id;
            
            $whatsapp->name        = $request->input('name');
            $whatsapp->type        = WhatsappDevice::BUSINESS;
            $whatsapp->credentials = $request->credentials;
            $whatsapp->save();
            $notify[] = ['success', 'Whatsapp business API credentials added successfully'];
            return back()->withNotify($notify);
        }

        if($access->is_allowed) {

            if ($type == "webhook") {
    
                $user = Auth::user();
                $user->webhook_token = $request->input("verify_token");
                $user->save();
                $notify[] = ['success', 'Whatsapp Cloud Webhook verify token saved successfully'];
            }

            if($whatsapp_device_count < $access->gateway_limit || $access->gateway_limit == 0) {

             
                if($request->has("min_delay") && $request->has("max_delay")) { 
                    
                    $validate['min_delay'] = 'required|gte:10';
                    $validate['max_delay'] = ['required', 'gt:min_delay', new DifferenceRule($request->min_delay)];
                    
                    $currentUrl = $request->root();
                    $parsedUrl = parse_url($currentUrl);
                    $domain = $parsedUrl['host'];
        
                    $response = Http::post(env('WP_SERVER_URL').'/sessions/init', [ 
                        'domain' => $domain
                    ]);
        
                    $responseBody = json_decode($response->body());
            
                    if ($response->status() === 200 && $responseBody->success) {
        
                        $credentials = [];
                        $credentials['min_delay']   = $request->input('min_delay');
                        $credentials['max_delay']   = $request->input('max_delay');
                        $credentials['number']      = " ";
                        $credentials['multidevice'] = "YES";
                        $whatsapp = new WhatsappDevice();
                        $whatsapp->user_id = auth()->user()->id;
                        $whatsapp->name = randomNumber()."_".$request->input('name');
                        $whatsapp->credentials =  $credentials;
                        $whatsapp->type =  WhatsappDevice::NODE;
                        
                        $whatsapp->status = 'initiate';
                        $whatsapp->save();
                        $notify[] = ['success', 'Whatsapp Device successfully added'];
        
                    } else {
        
                        $notify[] = ['error', $responseBody->message];
                    } 
                }
                return back()->withNotify($notify);
            } else {
                $notify[] = ['error', "Plan does not allow you to create more than $access->gateway_limit gateways"];
                return back()->withNotify($notify);
            }
            
        } else {
            $notify[] = ['error', "Current Plan doesn't allow you to add Whatsapp Devices"];
            return back()->withNotify($notify);
        }
        
    }

    /**
     * whatsapp template fetch method
     * @param Request $request, $type
     * @return mixed
     */
    public function cloudFetch(Request $request, $type = null) {
		
        $user = Auth::user();
		$templates = WhatsappTemplate::where("user_id", $user->id)->where("cloud_id", $request->input("cloud_id"))
                              ->whereIn("status", ["APPROVED"])
                              ->get();
		return response()->json(['templates' => $templates]);
	}

    /**
     * whatsapp cloud api templates
     * @param Request $request, $type, $id
     * @return view
     */
    public function cloudTemplate(Request $request, $type = null, $id = null) {
        
        $user = Auth::user();
        $templates = [];
		$title 	   = strtoupper(@$type)." ".translate("Template List");
		if($type = "whatsapp") {

			$templates = $id ? WhatsappTemplate::where("user_id", $user->id)->where("cloud_id", $id)->latest()->paginate(paginateNumber(site_settings("paginate_number"))) :  WhatsappTemplate::where($user->id)->latest()->paginate(paginateNumber(site_settings("paginate_number")));
            return view('user.template.whatsapp.index', compact('title', 'templates'));
		}
    }

    /**
     * whatsapp cloud api templates
     * @param Request $request
     * @return view
     */
    public function cloudRefresh(Request $request) {

		try {
            
            $user = Auth::user();
			$itemId = $request->input("itemId");
			WhatsappTemplate::where('cloud_id', $itemId)->delete();
			$whatsapp_business_account = WhatsappDevice::find($itemId);
			$credentials 			   = $whatsapp_business_account->credentials;
			$token 					   = $credentials['user_access_token'];
			$waba_id 				   = $credentials['whatsapp_business_account_id'];
			$url 					   = "https://graph.facebook.com/v19.0/$waba_id/message_templates";

			$queryParams = [
				'fields' => 'name,category,language,quality_score,components,status',
				'limit'  => 100
			];

			$headers = [
				'Authorization' => "Bearer $token"
			];

			$response 	  = Http::withHeaders($headers)->get($url, $queryParams);
			$responseData = $response->json();
            
			if (array_key_exists("data", $responseData)) {
				
				foreach ($responseData["data"] as $template) {
					
					$template_data = [
						'cloud_id'      	   => $itemId,
						'user_id'       	   => $user->id,
						'language_code' 	   => $template["language"],
						'name' 	   			   => $template["name"],
						'category'	    	   => $template["category"],
						'status'        	   => $template["status"],
						'template_information' => $template["components"]
					];

					WhatsappTemplate::create($template_data);
				}

				return json_encode([
					'reload' => true,
					'status' => true,
				]);
				
			} else {

				return json_encode([
					'reload' => true,
					'status' => false,
				]);
			}

		} catch (\Exception $e) {
            
			return json_encode([
				'reload' => true,
				'status' => false,
			]);
		}
	}

    /**
     * whatsapp update method
     * @param Request $request
     * @return mixed
     */
    public function update(Request $request) {

        $validate         = [];
        $notify           = [];
        if($request->has("whatsapp_business_api") && $request->input("whatsapp_business_api") == true) {

            $required_credentials = config("setting.whatsapp_business_credentials.required");

            foreach($required_credentials as $required_cred_key => $required_cred_value) {

                $validate['credentials'][$required_cred_key] = 'required';
            }
        }

        if($request->has("whatsapp_node_module") && $request->input("whatsapp_node_module") == true) { 
            
            $validate['min_delay'] = 'required|gte:10';
            $validate['max_delay'] = ['required', 'gt:min_delay', new DifferenceRule($request->min_delay)];
        }
        $request->validate($validate);

        $userId     = auth()->user()->id;
        $whatsapp    = WhatsappDevice::where('user_id',$userId)
            ->where('id', $request->input('id'))
            ->first();

        $credentials = $whatsapp->credentials;
       
        if($request->input("whatsapp_business_api") == true) {

            $credentials = $request->input("credentials");
            $whatsapp->user_id    = $userId;
            $whatsapp->admin_id    = null;
            $whatsapp->name        = $request->input('name');
            $whatsapp->credentials = $credentials;
            $whatsapp->update();
            $notify[] = ['success', 'Whatsapp business API credentials updated successfully'];
        } 
        
        if($request->input("whatsapp_node_module") == true) {

            $whatsapp->user_id  = $userId;
            $whatsapp->admin_id  = null;
            
            $credentials['min_delay']   = $request->input('min_delay');
            $credentials['max_delay']   = $request->input('max_delay');
            $whatsapp->credentials      = $credentials;
            $whatsapp->update();
            $notify[] = ['success', 'WhatsApp device updated successfully.'];
        }
        
        return back()->withNotify($notify);
    }

    /**
     * whatsapp delete method
     * @param Request $request
     * @return mixed
     */
    public function delete(Request $request)
    {
        $whatsapp = WhatsappDevice::where('id', $request->id)->where('user_id', auth()->user()->id)->first();
        $whatsapp->delete();
        if ($whatsapp->type == WhatsappDevice::BUSINESS) {
            $notify[] = ['success', 'WhatsApp Business Portfolio has been successfully deleted.'];
            return back()->withNotify($notify);
        }
        try {
            $response = Http::delete(env('WP_SERVER_URL').'/sessions/delete/'.$whatsapp->name);
            if ($response->status() == 200) {
                $notify[] = ['success', 'WhatsApp device has been successfully deleted, and the associated sessions have been cleared from the node.'];
            }else{
                $notify[] = ['success', 'WhatsApp device has been successfully deleted.'];
            }
        } catch (\Exception $e) {
            $notify[] = ['error', 'Oops! Something went wrong. The Node server is not running.'];
            return back()->withNotify($notify);
        }
        return back()->withNotify($notify);
    }

    /**
     * whatsapp device status update method
     * @param Request $request
     * @return false|string
     */
    public function statusUpdate(Request $request)
    {
        $whatsapp = WhatsappDevice::where('id', $request->id)->where('user_id', auth()->user()->id)->first();

        if ($request->status=='connected') {
            try {
                $findWhatsappsession = Http::get(env('WP_SERVER_URL').'/sessions/status/'.$whatsapp->name);
                if ($findWhatsappsession->status()==200) {
                    $whatsapp->status = 'connected';
                    $message = "Successfully whatsapp sessions reconnect";
                }else{
                    $response = Http::delete(env('WP_SERVER_URL').'/sessions/delete/'.$whatsapp->name);
                    $whatsapp->status = 'disconnected';
                    $message = "Successfully whatsapp sessions disconnected";
                }
                $whatsapp->update();
            } catch (\Exception $e) {

            }
        }elseif ($request->status=='disconnected') {
            try {
                $response = Http::delete(env('WP_SERVER_URL').'/sessions/delete/'.$whatsapp->name);
                if ($response->status() == 200) {
                    $whatsapp->status = 'disconnected';
                    $message =  'Whatsapp Device successfully Deleted';
                }else{
                    $message =  'Opps! Something went wrong, try again';
                }
                $whatsapp->update();
            } catch (\Exception $e) {

            }
        }else{
            try {
                $response = Http::delete(env('WP_SERVER_URL').'/sessions/delete/'.$whatsapp->name);
                if ($response->status() == 200) {
                    $whatsapp->status = 'disconnected';
                    $message = 'Whatsapp Device successfully Deleted';
                }else{
                    $message ='Opps! Something went wrong, try again';
                }
                $whatsapp->update();
            } catch (\Exception $e) {

            }
            $whatsapp->status = $request->status;
            $whatsapp->update();
        }

        return json_encode([
            'success' => $message
        ]);
    }

    /**
     * whatsapp qr quote scan method
     * @param Request $request
     * @return false|string
     */
    public function getWaqr(Request $request) {
       
        $whatsapp = WhatsappDevice::where('id', $request->id)->where('user_id', auth()->user()->id)->first();
        $islegacy = !(array_key_exists("multidevice", $whatsapp->credentials) && $whatsapp->credentials["multidevice"] ? $whatsapp->credentials["multidevice"] : 'NO' === "YES");
        $data = array();
        $currentUrl = $request->root();
        $parsedUrl = parse_url($currentUrl);
        $domain = $parsedUrl['host'];
        
        $response = Http::post(env('WP_SERVER_URL').'/sessions/create', [
            'id' => $whatsapp->name,
            'isLegacy' => $islegacy,
            'domain' => $domain
        ]);
        
        $responseBody = json_decode($response->body());
       
        if ($response->status() === 200) {
            $data['status'] = $response->status();
            $data['qr'] = $responseBody->data->qr;
            $data['message'] = $responseBody->message;
        } else {
            $msg = $response->status() === 500 ? "Invalid Software License" : "Trying to create a new Session";
            $data['status'] = $response->status();
            $data['qr'] = '';
            $data['message'] = $msg;
        }

        $response = [
            'response' => $whatsapp,
            'data' => $data
        ];

        return json_encode($response);
    }


    public function getDeviceStatus(Request $request): bool|string
    {
        $device_id   = $request->id;
        $whatsapp    = WhatsappDevice::where('id', $device_id)->where('user_id', auth()->user()->id)->first();
        $credentials = $whatsapp->credentials;
        $data = array();
        try {
            $checkConnection = Http::get(env('WP_SERVER_URL').'/sessions/status/'.$whatsapp->name);
            if ($whatsapp->status === "connected" || $checkConnection->status() === 200) {
                $whatsapp->status = 'connected';
                $res = json_decode($checkConnection->body());
                if (isset($res->data->wpInfo)) {
                    $wpNumber = str_replace('@s.whatsapp.net', '', $res->data->wpInfo->id);
                    $wpNumber = explode(':', $wpNumber);
                    $wpNumber = $wpNumber[0] ?? $whatsapp->credentials["number"];

                    try {
                        $credentials["number"] = $wpNumber;
                        $whatsapp->credentials = $credentials;
                    } catch (Exception $e) {
                        
                    }
                }
                $whatsapp->save();
                $data['status']  = 301;
                $data['qr']      = asset('assets/file/dashboard/image/done.gif');
                $data['message'] = 'Successfully connected WhatsApp device';
            }
        } catch (RequestException $e) {
            $data['status'] = $e->getCode();
            $data['qr'] = '';
            $data['message'] = $e->getMessage();
        } catch (\Exception $e) {
            $data['status'] = 500;
            $data['qr'] = '';
            $data['message'] = $e->getMessage();
        }

        $response = [
            'response' => $whatsapp,
            'data' => $data
        ];

        return json_encode($response);
    }
}
