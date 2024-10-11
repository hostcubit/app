<?php

namespace App\Http\Controllers\User;

use App\Enums\ServiceType;
use App\Enums\StatusEnum;
use Closure;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Service\Admin\Dispatch\SmsService;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\GeneralSetting;
use App\Models\WhatsappDevice;
use App\Service\Admin\Core\CustomerService;
use App\Service\Admin\Dispatch\WhatsAppService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\StoreWhatsAppRequest;
use App\Models\PricingPlan;
use App\Models\WhatsappTemplate;

class ManageWhatsappController extends Controller {

    public SmsService $smsService;
    public WhatsAppService $whatsAppService;
    public CustomerService $customerService;

    public function __construct(WhatsAppService $whatsAppService, SmsService $smsService, CustomerService $customerService) {

        $this->smsService      = $smsService;
        $this->whatsAppService = $whatsAppService;
        $this->customerService = $customerService;
        $this->middleware(function (Request $request, Closure $next) {
            
            if (Auth::user()->whatsapp_credit == 0 && in_array(str_replace('user.whatsapp.', '', Route::currentRouteName()),  ['send', 'store'])) {
               
                $notify[] = ['error', 'You no longer have sufficient Whatsapp credits. Please purchase a new subscription plan.'];
                return redirect()->route('user.dashboard')->withNotify($notify);
            }
            return $next($request);
        });
    }
    public function create() {

        $channel          = "whatsapp";
        $user             = Auth::user();
        $groups           = $user->group()->get();
        $templates        = $user->template()->get();
    	$title            = "Compose WhatsApp Massage";
        $allowed_access   = (object) planAccess($user); 
        if ($allowed_access->type == StatusEnum::FALSE->status()) {

            $whatsapp_node_devices  = WhatsappDevice::where("user_id", $user->id)->where('status', WhatsappDevice::CONNECTED)->where("type", StatusEnum::FALSE->status())->latest()->get();
            $whatsapp_bussiness_api = WhatsappDevice::where("user_id", $user->id)->where("type", StatusEnum::TRUE->status())->latest()->get();
            
            return view('user.whatsapp.create', compact('title', 'groups', 'templates', 'whatsapp_node_devices', 'whatsapp_bussiness_api', 'channel', 'allowed_access'));
        } else {

            $whatsapp_node_devices  = WhatsappDevice::where("user_id", $user->id)->where('status', WhatsappDevice::CONNECTED)->where("type", StatusEnum::FALSE->status())->latest()->get();
            return view('user.whatsapp.create', compact('title', 'groups', 'templates', 'whatsapp_node_devices', 'channel', 'allowed_access'));
        }
    }

    public function index() {

        $user         = Auth::user();
    	$title        = "WhatsApp History";
        $whatsAppLogs = WhatsappLog::where('user_id', $user->id)->orderBy('id', 'DESC')->with('whatsappGateway')->paginate(paginateNumber(site_settings("paginate_number")));

    	return view('user.whatsapp.index', compact('title', 'whatsAppLogs'));
    }

    public function store(StoreWhatsAppRequest $request) {
        
        
        $user    = Auth::user();
        $general = GeneralSetting::first();
        session()->put('old_wa_message',$request->input('message') ? $request->input('message') : "");

        $subscription = Subscription::where('user_id',$user->id)->where('status','1')->get();
        
        if (count($subscription) == 0) {

            $notify[] = ['error', 'Your Subscription Is Expired! Buy A New Plan'];
            return back()->withNotify($notify);
        }

      
        $allowed_access = (object) planAccess($user);
        
        if ($allowed_access->type == StatusEnum::FALSE->status()) {

          
            if ($request->input("cloud_api") == "true" ) {

                $allAvailableWaGateway = WhatsappDevice::where("id",$request->input("whatsapp_device_id"))->pluck("id")->toArray();
            
                $templateData    = WhatsappTemplate::find($request->input("whatsapp_template_id"));
                
                if(!$templateData) {
    
                    $notify[] = ['error', 'Template Unavailable'];
                    return back()->withNotify($notify);
                }
                
            } else {

                $this->whatsAppService->fileValidationRule(request());
                $allAvailableWaGateway = $request->input("whatsapp_device_id") == "-1" ? WhatsappDevice::where("user_id", $user->id)->where("type", WhatsappDevice::NODE)->where('status', 'connected')->pluck("credentials", "id")->toArray()
                                         : WhatsappDevice::where("user_id", $user->id)->where("id", $request->input("whatsapp_device_id"))->where("type", WhatsappDevice::NODE)->where('status', 'connected')->pluck("credentials", "id")->toArray();
               
            }
            if (!$allAvailableWaGateway) {

                $notify[] = ['error', 'User doesnt have any whatsapp gateway without cloud API'];
                return back()->withNotify($notify);
            }
            

            
        } else {
           
            if ($request->input("cloud_api") == "true" ) {

                $allAvailableWaGateway = WhatsappDevice::where("id",$request->input("whatsapp_device_id"))->pluck("id")->toArray();
            
                $templateData    = WhatsappTemplate::find($request->input("whatsapp_template_id"));
    
                if(!$templateData) {
    
                    $notify[] = ['error', 'Template Unavailable'];
                    return back()->withNotify($notify);
                }
                
            } else {

                $this->whatsAppService->fileValidationRule(request());
                $allAvailableWaGateway = $request->input("whatsapp_device_id") == "-1" ? WhatsappDevice::where("user_id", $user->id)->where("type", WhatsappDevice::NODE)->where('status', 'connected')->pluck("credentials", "id")->toArray()
                                         : WhatsappDevice::where("user_id", $user->id)->where("id", $request->input("whatsapp_device_id"))->where("type", WhatsappDevice::NODE)->where('status', 'connected')->pluck("credentials", "id")->toArray();
               
            }
            if (!$allAvailableWaGateway) {

                $notify[] = ['error', 'There are no connected devices at the moment'];
                return back()->withNotify($notify);
            }
        }

        if (!$request->input('number') && !$request->input('group_id') && !$request->has('file')) {

            $notify[] = ['error', 'Invalid number collect format'];
            return back()->withNotify($notify);
        }

        
        if (count($allAvailableWaGateway) < 1) {

            $notify[] = ['error', 'Not available WhatsApp Gateway'];
            return back()->withNotify($notify);
        }

        if ($request->has('file')) {

            $extension = strtolower($request->file('file')->getClientOriginalExtension());
            if (!in_array($extension, ['csv', 'xlsx'])) {

                $notify[] = ['error', 'Invalid file extension'];
                return back()->withNotify($notify);
            }
        }

        $allContactNumber = []; $numberGroupName  = [];

        $this->smsService->processNumber(request(), $allContactNumber);
        $this->smsService->processGroupId(request(), $allContactNumber, $numberGroupName, $user->id);
        $this->smsService->processFile(request(), $allContactNumber, $numberGroupName);

        $contactNewArray = $this->smsService->flattenAndUnique($allContactNumber);
        $wordLength      = $general->whatsapp_word_count;
        $messages        = str_split($request->input('message'),$wordLength);
        $totalCredit     = count($contactNewArray) * count($messages);

        if ($totalCredit > $user->whatsapp_credit) {

            $notify[] = ['error', 'You do not have a sufficient credit for send message'];
            return back()->withNotify($notify);
        }
        
        $this->customerService->deductCreditLog($user, (int)$totalCredit, ServiceType::WHATSAPP->value);
        $this->whatsAppService->save(\request(), $contactNewArray, (int)$wordLength, $numberGroupName, $allAvailableWaGateway, $user->id, $request->input("cloud_api") == "true" ? $templateData : null, $allowed_access);

        session()->forget('old_wa_message');

        $notify[] = ['success', 'New WhatsApp Message request sent, please see in the WhatsApp Log history for final status'];
        return redirect()->route('user.whatsapp.index')->withNotify($notify);
    }


    public function search(Request $request)
    {
        $user = Auth::user();
        $search = $request->input('search');
        $searchDate = $request->input('date');
        $status = $request->input('status');

        if (empty($search) && empty($searchDate) && empty($status)) {
            $notify[] = ['error', 'Search data field empty'];
            return back()->withNotify($notify);
        }

        $whatsAppLogs = $this->whatsAppService->searchWhatsappLog($search, $searchDate);
        $whatsAppLogs = $whatsAppLogs->where('user_id', $user->id)->paginate(paginateNumber(site_settings("paginate_number")));

        $title = 'Email History Search - ' . $search . ' '.$searchDate.' '.$status;
        return view('user.whatsapp.index', compact('title', 'whatsAppLogs', 'search', 'searchDate', 'status'));
    }

    public function statusUpdate(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:whatsapp_logs,id',
            'status' => 'required|in:1,4',
        ]);

        if($request->input('smslogid') !== null){
            $smsLogIds = array_filter(explode(",",$request->input('smslogid')));
            if(!empty($smsLogIds)){
                $this->whatsappLogStatusUpdate((int) $request->input('status'), (array) $smsLogIds);
            }
        }

        if($request->has('id')){
            $this->whatsappLogStatusUpdate((int) $request->input('status'), (array) $request->input('id'));
        }

        $notify[] = ['success', 'WhatsApp status has been updated'];
        return back()->withNotify($notify);
    }
    private function whatsappLogStatusUpdate(int $status, array $smsLogIds): void
    {
        $general = GeneralSetting::first();

        foreach($smsLogIds as $smsLogId){
            $smslog = WhatsappLog::find($smsLogId);

            if(!$smslog){
                continue;
            }

            $wordLength = $general->whatsapp_word_count;
            $user = User::find($smslog->user_id);

            if($status == WhatsappLog::PENDING && $user){
                $messages = str_split($smslog->message,$wordLength);
                $totalCredit = count($messages);

                if($user->credit > $totalCredit){
                    $smslog->status = $status;
                    $this->customerService->deductCreditLog($user, $totalCredit, ServiceType::WHATSAPP->value);
                }
            }else{
                $smslog->status = $status;
            }
            $smslog->save();
        }
    }

}

