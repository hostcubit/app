<?php

namespace App\Http\Controllers\Admin\Dispatch;

use App\Enums\ServiceType;
use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatsAppRequest;
use App\Service\Admin\Core\CurlService;
use App\Service\Admin\Core\CustomerService;
use App\Service\Admin\Dispatch\SmsService;
use App\Service\Admin\Dispatch\WhatsAppService;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Group;
use App\Models\GeneralSetting;
use App\Models\Template;
use App\Models\Contact;
use App\Models\WhatsappCreditLog;
use App\Models\WhatsappDevice;
use App\Models\WhatsappLog;
use App\Rules\MessageFileValidationRule;
use App\Service\FileProcessService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use App\Jobs\ProcessWhatsapp;
use App\Models\WhatsappTemplate;

class WhatsappController extends Controller
{

    public WhatsAppService $whatsAppService;
    public CustomerService $customerService;
    public SmsService $smsService;

    public function __construct(WhatsAppService $whatsAppService, SmsService $smsService, CustomerService $customerService) {

        $this->whatsAppService = $whatsAppService;
        $this->customerService = $customerService;
        $this->smsService      = $smsService;
    }

    public function index() {

        $title   = "All Whatsapp Message History";
        $smslogs = WhatsappLog::orderBy('id', 'DESC')->with('user', 'whatsappGateway')->paginate(paginateNumber(site_settings("paginate_number")));
        return view('admin.whatsapp_messaging.index', compact('title', 'smslogs'));
    }

    public function search(Request $request) {

        $search     = $request->input('search');
        $searchDate = $request->input('date');
        $status     = $request->input('status');

        if (empty($search) && empty($searchDate) && empty($status)) {

            $notify[] = ['error', 'Search data field empty'];
            return back()->withNotify($notify);
        }

        $smslogs = $this->whatsAppService->searchWhatsappLog($search, $searchDate);
        $smslogs = $smslogs->paginate(paginateNumber(site_settings("paginate_number")));
        $title   = 'Email History Search - ' . $search . ' '.$searchDate.' '.$status;
        return view('admin.whatsapp_messaging.index', compact('title', 'smslogs', 'search', 'searchDate', 'status'));
    }

    public function statusUpdate(Request $request) {
        
        $request->validate([
            'id'     => 'nullable|exists:whatsapp_logs,id',
            'status' => 'required|in:1,3,4',
        ]);

        $smsLogIds = $request->input('smslogid') !== null ? array_filter(explode(",",$request->input('smslogid'))) : $request->input('id');
        $this->whatsappLogStatusUpdate((int) $request->input('status'), (array) $smsLogIds);

        $notify[] = ['success', 'WhatsApp status has been updated'];
        return back()->withNotify($notify);
    }

    private function whatsappLogStatusUpdate(int $status, array $smsLogIds): void {

        $general   = GeneralSetting::first();
        $i         = 1; 
        $addSecond = 50;

        foreach(array_reverse($smsLogIds) as $smsLogId) {
            
            $log = WhatsappLog::find($smsLogId);

            if (!$log) {
                continue;
            }

            if ($log->status != WhatsappLog::PENDING) {

                $wordLength = $general->whatsapp_word_count;
                $user       = User::find($log->user_id);

                if($status == WhatsappLog::PENDING && $user) {

                    $whatsappGateway = WhatsappDevice::where('user_id', $log->user_id)->where('status', 'connected')->firstorFail();
                    $messages        = str_split($log->message,$wordLength);
                    $totalCredit     = count($messages);
                
                    if($user->whatsapp_credit >= $totalCredit) {

                        $log->status = $status;
                        $this->customerService->deductCreditLog($user, $totalCredit, ServiceType::WHATSAPP->value);
                    }
                } else {

                    $whatsappGateway  = $log->mode == WhatsappLog::NODE ? WhatsappDevice::where('admin_id', auth()->guard('admin')->user()->id)->where('status', 'connected')->firstorFail() : WhatsappDevice::find($log->whatsapp_id)->firstOrFail();
                    $log->whatsapp_id = $whatsappGateway->id;
                    $log->status      = $status;
                }

                if ($log->mode == WhatsappLog::NODE) {
                    $rand      = rand($whatsappGateway->min_delay ,$whatsappGateway->max_delay);
                    $addSecond = $i * $rand;
                }
                $log->save();

                if($log->status == WhatsappLog::PENDING) { 
                    
                    if($log->mode == WhatsappLog::NODE) {

                        ProcessWhatsapp::dispatch($log)->delay(Carbon::now()->addSeconds($addSecond));
                        $i++;
                    } else {
                        ProcessWhatsapp::dispatch($log);
                    }
                }
            }
        }
    }

    public function create() {

        $title            = "Compose WhatsApp Message";
        $templates        = Template::whereNull('user_id')->get();
        $groups           = Group::whereNull('user_id')->get();
        $whatsapp_node_devices  = WhatsappDevice::whereNull("user_id")->where('status', WhatsappDevice::CONNECTED)->where("type", StatusEnum::FALSE->status())->latest()->get();
        $whatsapp_bussiness_api = WhatsappDevice::whereNull("user_id")->where("type", StatusEnum::TRUE->status())->latest()->get();
        
        $channel          = "whatsapp";
        return view('admin.whatsapp_messaging.create', compact('title', 'groups', 'templates', 'whatsapp_node_devices', 'whatsapp_bussiness_api', 'channel'));
    }

    public function store(StoreWhatsAppRequest $request) {
        
        $whatsappGateway  = [];
        $numberGroupName  = []; 
        $allContactNumber = [];
        $general          = GeneralSetting::first();
        session()->put('old_message',$request->input('message') ?  $request->input('message') : "");

        if(!$request->input('number') && !$request->input('group_id') && !$request->has('file')) {

            $notify[] = ['error', 'Invalid number collect format'];
            return back()->withNotify($notify);
        }
        
        $this->smsService->processNumber(request(), $allContactNumber);
        $this->smsService->processGroupId(request(), $allContactNumber, $numberGroupName);
        $this->smsService->processFile(request(), $allContactNumber, $numberGroupName);

        $contactNewArray  = $this->smsService->flattenAndUnique($allContactNumber);
        $wordLength       = $general->whatsapp_word_count;

        if($request->input("cloud_api") == "true") {
            
            $whatsappGateway = WhatsappDevice::where("id",$request->input("whatsapp_device_id"))->pluck("id")->toArray();
            
            $templateData    = WhatsappTemplate::find($request->input("whatsapp_template_id"));

            if(!$templateData) {

                $notify[] = ['error', 'Template Unavailable'];
                return back()->withNotify($notify);
            }
            
            $this->whatsAppService->save(\request(), $contactNewArray, (int)$wordLength, $numberGroupName, $whatsappGateway, null, $templateData);
        } else {
            
            $this->whatsAppService->fileValidationRule(request());

            $whatsappGateway = $request->input("whatsapp_device_id") == "-1" ? WhatsappDevice::where('admin_id', auth()->guard('admin')->user()->id)->where('status', 'connected')->pluck("credentials", "id")->toArray()
                               : WhatsappDevice::where('admin_id', auth()->guard('admin')->user()->id)->where("id", $request->input("whatsapp_device_id"))->where('status', 'connected')->pluck("credentials", "id")->toArray();
            if (count($whatsappGateway) < 1) {
    
                $notify[] = ['error', 'Not available WhatsApp Gateway'];
                return back()->withNotify($notify);
            }

            $this->whatsAppService->save(\request(), $contactNewArray, (int)$wordLength, $numberGroupName, $whatsappGateway);
        }
        $notify[] = ['success', 'New WhatsApp Message request sent, please see in the WhatsApp Log history for final status'];

        session()->forget('old_message');
        return back()->withNotify($notify);
    }

    public function delete(Request $request)
    {
        $this->validate($request, [
            'id' => 'required'
        ]);

        $smsLog = WhatsappLog::findOrFail($request->id);
        $user = User::find($smsLog->user_id);

        if($user && $smsLog->status == 1) {

            $messages     = str_split($smsLog->message,160);
            $totalcredit  = count($messages);
            $user->credit += $totalcredit;
            $user->save();

            $creditInfo              = new WhatsappCreditLog();
            $creditInfo->user_id     = $smsLog->user_id;
            $creditInfo->type        = "+";
            $creditInfo->credit      = $totalcredit;
            $creditInfo->trx_number  = trxNumber();
            $creditInfo->post_credit =  $user->whatsapp_credit;
            $creditInfo->details     = $totalcredit." Credit Return ".$smsLog->to." is Falied";
            $creditInfo->save();
        }

        $smsLog->delete();
        $notify[] = ['success', "Successfully SMS log deleted"];
        return back()->withNotify($notify);
    }
}
