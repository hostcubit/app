<?php

namespace App\Http\Controllers\User;

use App\Enums\ServiceType;
use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailRequest;
use App\Service\Admin\Core\CustomerService;
use App\Service\Admin\Dispatch\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\EmailLog;
use App\Models\Subscription;
use Illuminate\Support\Facades\Route;
use App\Models\Gateway;
use App\Models\PricingPlan;
use Closure;
use App\Models\User;
use App\Service\Admin\Dispatch\SmsService;

class ManageEmailController extends Controller {
    
    public EmailService $emailService;
    public SmsService $smsService;
    public CustomerService $customerService;
    
    public function __construct(EmailService $emailService, CustomerService $customerService, SmsService $smsService) {
        
        $this->middleware(function (Request $request, Closure $next) {
            
            if(Auth::user()->email_credit == 0 && in_array(str_replace('user.manage.email.', '', Route::currentRouteName()),  ['send', 'store'])) {
               
                $notify[] = ['error', 'You no longer have sufficient Email credits. Please purchase a new subscription plan.'];
                return redirect()->route('user.dashboard')->withNotify($notify);
            }
            return $next($request);
        });

        $this->emailService = $emailService;
        $this->customerService = $customerService;
        $this->smsService      = $smsService;
    }

    public function create() {

        $user = Auth::user();
        $title = "Compose Email";
        $emailGroups = $user->emailGroup()->get();
        $credentials = config('setting.gateway_credentials.email');
        $allowed_access = planAccess($user);
        $channel     = "email";
        if($allowed_access) {
            $allowed_access = (object)planAccess($user);
        } else {
            $notify[] = ['error','Please Purchase A Plan'];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        if($allowed_access->type == StatusEnum::FALSE->status() && $user->gateway->isNotEmpty() && $user->gateway()->mail()->active()->exists()) {

            return view('user.email.create', compact('title', 'emailGroups', 'credentials', 'user', 'allowed_access', 'channel'));
        } elseif($allowed_access->type == StatusEnum::TRUE->status()) {
            return view('user.email.create', compact('title', 'emailGroups', 'credentials', 'user', 'allowed_access', 'channel'));
        }
        else{
            $notify[] = ['error', 'Can Not Compose Mail. No Active Gateway Found'];
            return back()->withNotify($notify);
        }
        
        
        
    }

    public function index()
    {
    	$title = "All Email History";
        $user = Auth::user();
        $emailLogs = EmailLog::where('user_id', $user->id)->orderBy('id', 'DESC')->with('sender')->paginate(paginateNumber(site_settings("paginate_number")));
    	return view('user.email.index', compact('title', 'emailLogs'));
    }

    public function store(StoreEmailRequest $request)
    {
        
        $user = Auth::user();
        $allowed_access   = (object) planAccess($user);
        if($user->email_credit == 0) {
            
            $notify[] = ['error', 'Not enough Email Credits, please purchase a new plan.'];
            return back()->withNotify($notify);
        }

        $defaultGateway = $allowed_access->type == StatusEnum::FALSE->status() ? Gateway::whereNotNull('mail_gateways')->where("user_id", $user->id)->where('is_default', 1)->first()
                          : Gateway::whereNotNull('mail_gateways')->whereNull("user_id")->where('is_default', 1)->first();
        
        if($request->input('gateway_type')) {

            $emailMethod = Gateway::where('id', $request->input('gateway_id'))->firstOrFail();
        }
        else{
            if($defaultGateway) {
                $emailMethod = $defaultGateway;
            }
            else {
                $notify[] = ['error', 'You Do Not Have Any Default Gateway.'];
                return back()->withNotify($notify);
            }
        }
        
        $subscription = Subscription::where('user_id',$user->id)
            ->where('status','1')
            ->get();

        if(count($subscription) == 0){
            $notify[] = ['error', 'Your Subscription Is Expired! Buy A New Plan'];
            return back()->withNotify($notify);
        }

        if(!$user->email){
            $notify[] = ['error', 'Please add your email from profile'];
            return back()->withNotify($notify);
        }

        if(!$request->input('email') && !$request->input('group_id') && !$request->has('file')){
            $notify[] = ['error', 'Invalid email format'];
            return back()->withNotify($notify);
        }

        if($request->has('file')) {
            $extension = strtolower($request->file('file')->getClientOriginalExtension());
            if (!in_array($extension, ['csv', 'xlsx'])) {
                $notify[] = ['error', 'Invalid file extension'];
                return back()->withNotify($notify);
            }
        }
        $numberGroupName = []; $allContactNumber = [];
        $this->emailService->processEmail($request,$allContactNumber, $user->id);
        $this->smsService->processGroupId($request, $allContactNumber, $numberGroupName, $user->id);
        $this->smsService->processFile($request, $allContactNumber, $numberGroupName);
       
        $emailAllNewArray = $this->emailService->flattenAndUnique($allContactNumber);
       
        

        if(count($emailAllNewArray) > $user->email_credit){
            $notify[] = ['error', 'You do not have a sufficient email credit for send mail'];
            return back()->withNotify($notify);
        }

        $this->customerService->deductCreditLog($user, count($emailAllNewArray), ServiceType::EMAIL->value);
        $this->emailService->sendEmail($emailAllNewArray, $emailMethod, $request, $numberGroupName, $user->id);

        $notify[] = ['success', 'New Email request sent, please see in the Email history for final status'];

        return redirect()->route('user.manage.email.index')->withNotify($notify);
    }

    public function view($id)
    {
        $title = "Details View";
        $user = Auth::user();
        $emailLogs = EmailLog::where('id',$id)->where('user_id',$user->id)->orderBy('id', 'DESC')->limit(1)->first();
        return view('partials.email_view', compact('title', 'emailLogs'));
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

        $emailLogs = $this->emailService->searchEmailLog($search, $searchDate);
        $emailLogs = $emailLogs->where('user_id', $user->id)->paginate(paginateNumber(site_settings("paginate_number")));

        $title = 'Email History Search - ' . $search;
        return view('user.email.index', compact('title', 'emailLogs', 'search', 'status'));
    }

    public function emailStatusUpdate(Request $request)
    {
       
        $request->validate([
            'id' => 'nullable|exists:email_logs,id',
            'status' => 'required|in:1,4',
        ]);
      
        if($request->input('email_log_id') !== null){
            $emailLogIds = array_filter(explode(",",$request->input('email_log_id')));
           
            if(!empty($emailLogIds)){
                $this->emailLogStatusUpdate((int) $request->input('status'),  $emailLogIds);
            }
        }

        if($request->has('id')){
            $this->emailLogStatusUpdate((int) $request->status, (array) $request->input('id'));
        }

        $notify[] = ['success', 'Email status has been updated'];
        return back()->withNotify($notify);
    }

    private function emailLogStatusUpdate(int $status, array $emailLogIds): void
    {

        foreach($emailLogIds as $emailLogId){
            $emailLog = EmailLog::find($emailLogId);
          
            if(!$emailLog){
               
                continue;
            }

            if($status == 1){
             
                if($emailLog->user_id) {

                    $user = User::find($emailLog->user_id);
                    if($user->email_credit > 1) {
                        $this->customerService->deductCreditLog($user, 1, ServiceType::EMAIL->value);

                        $emailLog->status = $status;
                        $emailLog->update();
                    }
                }
            }
            else{
              
                $emailLog->status = $status;
               
                $emailLog->update();
            }
        }
    }

    //Select Gateway
    public function selectGateway(Request $request) {
        
        $user = Auth::user();
        $allowed_access = planAccess($user);
        if($allowed_access) {
            $allowed_access = (object)planAccess($user);
        } else {
            $notify[] = ['error','Please Purchase A Plan'];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }
                            
        

        return response()->json($rows);
    }

}
