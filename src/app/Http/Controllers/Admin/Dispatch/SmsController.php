<?php

namespace App\Http\Controllers\Admin\Dispatch;

use App\Enums\AndroidApiSimEnum;
use Exception;
use App\Models\User;
use App\Models\Group;
use App\Models\SMSlog;
use App\Models\Gateway;
use App\Models\Template;
use App\Models\CreditLog;
use App\Models\AndroidApi;
use App\Models\SmsGateway;
use App\Service\Admin\Dispatch\SmsService;
use Illuminate\Http\Request;
use App\Models\GeneralSetting;
use App\Models\AndroidApiSimInfo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSMSRequest;

class SmsController extends Controller
{
    public SmsService $smsService;

    /**
     * Initializes SmsService dependency.
     *
     * @param SmsService $smsService The SMS service.
     */
    public function __construct(SmsService $smsService) {

        $this->smsService = $smsService;
    }

    /**
     * Displays all SMS history.
     *
     * @return \Illuminate\Contracts\View\View View for displaying SMS history.
     */
    public function index() {

    	$title            = "All SMS History";
    	$smslogs          = SMSlog::orderBy('id', 'DESC')->with('user', 'androidGateway', 'smsGateway')->paginate(paginateNumber(site_settings("paginate_number")));
        $android_gateways = AndroidApi::where("status", AndroidApiSimEnum::ACTIVE->value)->latest()->get();
    	return view('admin.sms.index', compact('title', 'smslogs', 'android_gateways'));
    }

    /**
     * Searches SMS history based on provided criteria.
     *
     * @param Request $request object containing search parameters.
     * @return \Illuminate\Contracts\View\View View for displaying search results.
     */
    public function search(Request $request) {

        $search     = $request->input('search');
        $searchDate = $request->input('date');
        $status     = $request->input('status');

        if (empty($search) && empty($searchDate) && empty($status)) {
            $notify[] = ['error', 'Search data field empty'];
            return back()->withNotify($notify);
        }

        $smslogs = $this->smsService->searchSmsLog($search, $searchDate);
        $smslogs = $smslogs->paginate(paginateNumber(site_settings("paginate_number")));
        $title   = 'Email History Search - ' . $search . ' '.$searchDate.' '.$status;

        return view('admin.sms.index', compact('title', 'smslogs', 'search', 'searchDate', 'status'));
    }

    /**
     * Updates SMS status based on the provided criteria.
     *
     * @param Request $request object containing update parameters.
     * @return \Illuminate\Http\RedirectResponse Redirect back with notification.
     */
    public function smsStatusUpdate(Request $request) {
        
        $request->validate([
            'smslogid' => 'nullable|exists:s_m_slogs,id',
            'status'   => 'required|in:1,3,4',
        ]);
        
        $general   = GeneralSetting::first();
        $smsLogIds = $request->input('smslogid') !== null ? array_filter(explode(",",$request->input('smslogid'))) : $request->has('smslogid');
        $this->smsService->smsLogStatusUpdate((int) $request->status, (array) $smsLogIds, $general, $request->input('android_sim_update') == "true" ? $request->input('sim_id') : null);

        $notify[] = ['success', 'SMS status has been updated'];
        return back()->withNotify($notify);
    }

    /**
     * Prepares data for composing a new SMS.
     *
     * @return \Illuminate\Contracts\View\View View for composing SMS.
     */
    public function create() {
        
        session()->forget(['old_sms_message', 'number']);

        $channel          = "sms";
        $title            = "Compose SMS";
        $general          = GeneralSetting::first();
        $templates        = Template::whereNull('user_id')->get();
        $groups           = Group::whereNull('user_id')->get();
        $credentials      = SmsGateway::orderBy('id','asc')->get();
        $android_gateways = AndroidApi::whereNull("user_id")->where("status", AndroidApiSimEnum::ACTIVE->value)->latest()->get();
        
        return view('admin.sms.create', compact('title', 'general', 'groups', 'templates', 'credentials', 'android_gateways', 'channel'));
    }

    /**
     * Stores a new SMS message.
     *
     * @param StoreSMSRequest $request object containing SMS data.
     * @return \Illuminate\Http\RedirectResponse Redirect back with notification.
     */
    public function store(StoreSMSRequest $request):mixed {

        session()->forget(['old_sms_message', 'number']);
        $allAvailableSims = [];
        $general          = GeneralSetting::first();
       
        if ($general->sms_gateway == 2) {

            $allAvailableSims = AndroidApi::whereNull('user_id')->whereHas('simInfo', function ($query) {

                $query->where('status', AndroidApiSimEnum::ACTIVE->value);
            })->with('simInfo')->get()->flatMap(function ($androidApi) {
    
                return $androidApi->simInfo->pluck('id')->toArray();
            })->toArray();

            $smsGateway = null;

            if (empty($allAvailableSims)) {

                $notify[] = ['error', 'No active sim connection detected!'];
                return back()->withNotify($notify);
            }
        } else {

            $defaultGateway = Gateway::whereNotNull('sms_gateways')->whereNull('user_id')->where('is_default', 1)->first();
            if ($request->input('gateway_id')) {

                $smsGateway = Gateway::whereNotNull('sms_gateways')->whereNull('user_id')->where('id',$request->gateway_id)->firstOrFail();
            }
            else {
                if ($defaultGateway) {
                    $smsGateway = $defaultGateway;
                }
                else {
                    $notify[] = ['error', 'No Available Default SMS Gateway'];
                    return back()->withNotify($notify);
                }
            }
            if (!$smsGateway) {

                $notify[] = ['error', 'Invalid Sms Gateway'];
                return back()->withNotify($notify);
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

        $numberGroupName = []; $allContactNumber = [];
        $this->smsService->processNumber($request, $allContactNumber);
        $this->smsService->processGroupId($request, $allContactNumber, $numberGroupName);
        $this->smsService->processFile($request, $allContactNumber, $numberGroupName);
        $contactNewArray = $this->smsService->flattenAndUnique($allContactNumber);
        $this->smsService->sendSMS($contactNewArray, $general, $smsGateway, $request, $numberGroupName, $allAvailableSims, null);
        
        $notify[] = ['success', 'New SMS request sent, please see in the SMS history for final status'];
        return back()->withNotify($notify);
    }
    
    /**
     * Deletes an SMS log entry.
     *
     * @param Request $request object containing the ID of the SMS log to be deleted.
     * @return \Illuminate\Http\RedirectResponse Redirect back with notification.
     */
    public function delete(Request $request) {

        $this->validate($request, [
            'id' => 'required'
        ]);

        try {
            $general    = GeneralSetting::first();
            $smsLog     = SMSlog::findOrFail($request->id);
            $wordLenght = $smsLog->sms_type == 1 ? $general->sms_word_text_count : $general->sms_word_unicode_count;

            if ($smsLog->status == 1) {

                $user = User::find($smsLog->user_id);
                if ($user) {

                    $messages      = str_split($smsLog->message,$wordLenght);
                    $totalcredit   = count($messages);
                    $user->credit += $totalcredit;
                    $user->save();

                    $creditInfo              = new CreditLog();
                    $creditInfo->user_id     = $smsLog->user_id;
                    $creditInfo->credit_type = "+";
                    $creditInfo->credit      = $totalcredit;
                    $creditInfo->trx_number  = trxNumber();
                    $creditInfo->post_credit = $user->credit;
                    $creditInfo->details     = $totalcredit." Credit Return ".$smsLog->to." is Falied";
                    $creditInfo->save();
                }
            }
            
            $smsLog->delete();
            $notify[] = ['success', "Successfully SMS log deleted"];
        } catch (Exception $e) {
            $notify[] = ['error', "Error occur in SMS delete time. Error is ".$e->getMessage()];
        }
        return back()->withNotify($notify);
    }
}
