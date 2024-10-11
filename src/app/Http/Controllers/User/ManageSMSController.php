<?php

namespace App\Http\Controllers\User;

use App\Enums\AndroidApiSimEnum;
use App\Enums\ServiceType;
use App\Enums\StatusEnum;
use Closure;
use App\Models\SMSlog;
use App\Models\Gateway;
use App\Models\SmsGateway;
use App\Models\AndroidApi;
use App\Models\PricingPlan;
use App\Service\Admin\Dispatch\SmsService;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\GeneralSetting;
use App\Service\Admin\Core\CustomerService;
use App\Models\AndroidApiSimInfo;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\StoreSMSRequest;

class ManageSMSController extends Controller
{
    public SmsService $smsService;
    public CustomerService $customerService;
    
    public function __construct(SmsService $smsService, CustomerService $customerService) {

        $this->smsService      = $smsService;
        $this->customerService = $customerService;
        $this->middleware(function (Request $request, Closure $next) {
            
            if(Auth::user()->credit == 0 && in_array(str_replace('user.sms.', '', Route::currentRouteName()),  ['send', 'store'])) {
                
                $notify[] = ['error', 'You no longer have sufficient SMS credits. Please purchase a new subscription plan.'];
                return redirect()->route('user.dashboard')->withNotify($notify);
            }
            return $next($request);
        });
    }

    public function create() {

        $channel          = "sms";
    	$user             = Auth::user();
        $title            = "Compose SMS";
        $groups           = $user->group()->get();
        $templates        = $user->template()->get();
        $credentials      = SmsGateway::orderBy('id','asc')->get();
        $allowed_access   = planAccess($user);
        $android_gateways = AndroidApi::where("user_id", auth()->user()->id)->where("status", AndroidApi::ACTIVE)->latest()->get();

        if ($allowed_access) {

            $allowed_access = (object)planAccess($user);
        } else {

            $notify[] = ['error','Please Purchase A Plan'];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        if ($allowed_access->type == StatusEnum::TRUE->status() || ($user->gateway->isNotEmpty() && $user->gateway()->sms()->active()->exists()) || auth()->user()->sms_gateway == 2) {
    
            return view('user.sms.create', compact('title','groups', 'templates', 'credentials', 'user', 'allowed_access', 'android_gateways', 'channel'));
        }
        else {

            $notify[] = ['error', 'Can Not Compose SMS. No Active Gateway Found'];
            return back()->withNotify($notify);
        }
    }

    public function index() {

    	$title          = "SMS History";
        $user           = Auth::user();
        $smslogs        = SMSlog::where('user_id', $user->id)->orderBy('id', 'DESC')->with('smsGateway', 'androidGateway')->paginate(paginateNumber(site_settings("paginate_number")));
        $allowed_access = (object) planAccess($user);
    	return view('user.sms.index', compact('title', 'smslogs', 'allowed_access'));
    }

    public function store(StoreSMSRequest $request) {
       
        $user             = Auth::user();
        $subscription     = Subscription::where('user_id',$user->id)->where('status','1')->count();
        $general          = GeneralSetting::first();
        $wordLength       = $request->input('sms_type') == "plain" ? $general->sms_word_text_count : $general->sms_word_unicode_count;
        $allowed_access   = (object) planAccess($user);
        $allAvailableSims = [];

        if ($subscription == 0) {

            $notify[] = ['error', 'Your Subscription Is Expired! Buy A New Plan'];
            return back()->withNotify($notify);
        }

        if (auth()->user()->sms_gateway == 2) {

            $smsGateway = null;

            if ($allowed_access->type == StatusEnum::FALSE->status()) {

                $allAvailableSims = AndroidApi::where('user_id', $user->id)
                ->whereHas('simInfo', function ($query) {
    
                    $query->where('status', AndroidApiSimEnum::ACTIVE->value);
                })
                ->with('simInfo')->get()->flatMap(function ($androidApi) {
    
                    return $androidApi->simInfo->pluck('id')->toArray();
                })
                ->toArray();
    
                if(!$allAvailableSims){
                    $notify[] = ['error', 'No active sim connection detected!'];
                    return back()->withNotify($notify);
                }
            } else {
    
                $allAvailableSims = AndroidApi::whereNull('user_id')
                ->whereHas('simInfo', function ($query) {
    
                    $query->where('status', AndroidApiSimInfo::ACTIVE);
                })
                ->with('simInfo')->get()->flatMap(function ($androidApi) {
    
                    return $androidApi->simInfo->pluck('id')->toArray();
                })
                ->toArray();
    
                if (!$allAvailableSims) {
    
                    $notify[] = ['error', 'Admin does not have any active SIM connection'];
                    return back()->withNotify($notify);
                }
            }
        }
        else {
            
            $defaultGateway = $allowed_access->type == StatusEnum::FALSE->status() ? Gateway::whereNotNull('sms_gateways')->where("user_id", $user->id)->where('is_default', 1)->first()
                              : Gateway::whereNotNull('sms_gateways')->whereNull("user_id")->where('is_default', 1)->first();
        
            if ($request->input('gateway_type')) {
    
                $smsGateway = Gateway::where('id', $request->input('gateway_id'))->first();
            }
            else {
                if ($defaultGateway) {

                    $smsGateway = $defaultGateway;
                }
                else {

                    $notify[] = ['error', 'You Do Not Have Any Default Gateway.'];
                    return back()->withNotify($notify);
                }
            }
        }
        if (!$request->input('number') && !$request->has('group_id') && !$request->has('file')) {

            $notify[] = ['error', 'Invalid number collect format'];
            return back()->withNotify($notify);
        }

        if ($request->has('file')) {
            $extension = strtolower($request->file('file')->getClientOriginalExtension());
            if (!in_array($extension, ['csv', 'xlsx'])) {
                $notify[] = ['error', 'Invalid file extension'];
                return back()->withNotify($notify);
            }
        }
        $numberGroupName  = []; $allContactNumber  = [];

        $this->smsService->processNumber($request, $allContactNumber, $user->id);
        $this->smsService->processGroupId($request, $allContactNumber, $numberGroupName, $user->id);
        $this->smsService->processFile($request, $allContactNumber, $numberGroupName, $user->id);

        $contactNewArray = $this->smsService->flattenAndUnique($allContactNumber);
        $totalMessage    = count(str_split($request->input('message'),$wordLength));
        $totalNumber     = count($contactNewArray);
        $totalCredit     = $totalNumber * $totalMessage;
  
        if ($totalCredit > $user->credit) {

            $notify[] = ['error', 'You do not have a sufficient credit for send message'];
            return back()->withNotify($notify);
        }

        $this->customerService->deductCreditLog($user, (int)$totalCredit, ServiceType::SMS->value);
        $this->smsService->sendSMS($contactNewArray, $general, $smsGateway, $request, $numberGroupName, $allAvailableSims, auth()->user()->id);
        session()->forget('user_sms_message');

        $notify[] = ['success', 'New SMS request sent, please see in the SMS history for final status'];
        return redirect()->route('user.sms.index')->withNotify($notify);
    }

    public function search(Request $request) {

        $user       = \auth()->user();
        $search     = $request->input('search');
        $searchDate = $request->input('date');
        $status     = $request->input('status');

        if (empty($search) && empty($searchDate) && empty($status)) {

            $notify[] = ['error', 'Search data field empty'];
            return back()->withNotify($notify);
        }

        $smslogs = $this->smsService->searchSmsLog($search, $searchDate);
        $smslogs = $smslogs->where('user_id', $user->id)
            ->orderBy('id','desc')
            ->with('user', 'androidGateway', 'smsGateway')
            ->paginate(paginateNumber(site_settings("paginate_number")));

        $title = 'Email History Search - ' . $search . ' '.$searchDate.' '.$status;
        return view('user.sms.index', compact('title', 'smslogs', 'search', 'searchDate', 'status'));
    }

    public function smsStatusUpdate(Request $request) {
       
        $request->validate([
            'id'     => 'nullable|exists:s_m_slogs,id',
            'status' => 'required|in:1,4',
        ]);
        $general    = GeneralSetting::first();
        $smsGateway = SmsGateway::where('id', $general->sms_gateway_id)->first();

        if (!$smsGateway) {

            $notify[] = ['error', 'Invalid Sms Gateway'];
            return back()->withNotify($notify);
        }

        if ($request->input('smslogid') !== null) {

            $smsLogIds = array_filter(explode(",",$request->input('smslogid')));
            if (!empty($smsLogIds)) {

                $this->smsService->smsLogStatusUpdate((int) $request->status, (array) $smsLogIds, $general, $smsGateway);
            }
        }

        if ($request->has('id')) {

            $this->smsService->smsLogStatusUpdate((int) $request->status, (array) $request->input('id'), $general, $smsGateway);
        }
        $notify[] = ['success', 'SMS status has been updated'];
        return back()->withNotify($notify);
    }
}
