<?php

namespace App\Jobs;

use App\Enums\CommunicationStatusEnum;
use App\Enums\StatusEnum;
use Illuminate\Support\Arr;
use App\Models\EmailLog;
use App\Models\User;
use App\Models\Admin;
use App\Models\GeneralSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Utility\SendEmail;
use App\Models\Gateway;
use App\Service\Admin\Core\CustomerService;
use App\Service\Admin\Dispatch\EmailService;
use Exception;
use SendGrid\Mail\TypeException;


class ProcessEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $emailLog;
    protected $emailService;
    protected $customerSevice;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($emailLog){

        $this->emailLog = $emailLog;
        $this->customerSevice = new CustomerService;
        $this->emailService = new EmailService($this->customerSevice);
    }


    /**
     * @return void
     * @throws TypeException
     */
    public function handle(): void
    {
        try {
            if ($this->emailLog->status != CommunicationStatusEnum::FAIL->value) {

                $emailMethod = Gateway::whereNotNull('mail_gateways')->where('status', StatusEnum::TRUE->status())->where('id', $this->emailLog->gateway_id)->first();
                $emailFrom   = $emailMethod->address;
                list($subject, $message, $email_to, $email_from_name, $email_reply_to) = $this->emailService->getEmailData($this->emailLog, $emailMethod);
                
    
                if($this->emailLog->sender->type == 'smtp') {

                    SendEmail::sendSMTPMail($email_to, $email_reply_to, $subject, $message, $this->emailLog,  $emailMethod, $email_from_name);
                }
                elseif($this->emailLog->sender->type == "mailjet") {
                    
                    SendEmail::sendMailJetMail($emailFrom, $subject, $message, $this->emailLog, $emailMethod);
                }
                elseif($this->emailLog->sender->type == "aws") {
                    SendEmail::sendSesMail($emailFrom, $subject, $message, $this->emailLog, $emailMethod); 
                }
                elseif($this->emailLog->sender->type  == "mailgun") {
                    
                    SendEmail::sendMailGunMail($emailFrom, $subject, $message, $this->emailLog, $emailMethod); 
                }
                elseif($this->emailLog->sender->type == "sendgrid") {
                    
                    SendEmail::sendGrid($emailFrom, $email_from_name, $email_to, $subject, $message, $this->emailLog, @$emailMethod->mail_gateways->secret_key);
                }
            }
        } catch(\Exception $exception) {
            \Log::error("Process Email failed: " . $exception->getMessage());
        }
    }
}
