<?php
namespace App\Service\Admin\Gateway;

use App\Models\Gateway;
use App\Enums\StatusEnum;
use App\Http\Utility\SendMail;
use App\Models\Template;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class EmailGatewayService
{
    public function save($request, $user_id = null) {
        
        $data = $this->param($request, $user_id);

        $gateway = Gateway::updateOrCreate([

            'id' => $request->input('id'),
            
        ], $data);

        return $gateway;
    }

    public function param($request, $user_id) {

        $data = [
            'user_id'            => $user_id,
            'type' 			     => $request->input('type'),
            'name'               => $request->input('name'),
            'address'            => $request->input('address'),
            'mail_gateways'      => $request->input('driver_information'),
        ];
        return $data;
    }

    public function statusUpdate($request, $user_id = null) {
        
        try {
            $status   = true;
            $reload   = false;
            $message  = translate('Gateway status updated successfully');
            $gateway  = Gateway::whereNotNull("mail_gateways")->where("user_id", $user_id)->where("id",$request->input('id'))->first();
            $column   = $request->input("column");
            
            if($column != "is_default" && $request->value == StatusEnum::TRUE->status()) {

                $reload = true;
                $gateway->status     = StatusEnum::FALSE->status();
                $gateway->is_default = StatusEnum::FALSE->status();
                $gateway->update();

            } elseif($column != "is_default" && $request->value == StatusEnum::FALSE->status()) {

                $gateway->status = StatusEnum::TRUE->status();
                $gateway->update();

            } elseif($column == "is_default") {
                
                $reload  = true;
                $message = translate('Default gateway updated successfully');
                Gateway::whereNotNull("mail_gateways")->where("user_id", $user_id)->where('id', '!=',$request->id)->update(["is_default" => StatusEnum::FALSE->status()]);
                $gateway->status = StatusEnum::TRUE->status();
                $gateway->$column = StatusEnum::TRUE->status();
                $gateway->update();
            } else {

                $status = false;
                $message = translate("Something went wrong while updating this gateway");
            }

        } catch (\Exception $error) {

            $status  = false;
            $message = $error->getMessage();
        }

        return json_encode([
            'reload'  => $reload,
            'status'  => $status,
            'message' => $message
        ]);
    }

    public function gatewayTest($gateway, $email_to) {

        $response      = " ";
        $emailTemplate = Template::where('slug', 'TEST_MAIL')->first();
        $messages      = str_replace("{{name}}", site_settings('site_name'), $emailTemplate->template_data['mail_body']);
        $messages      = str_replace("{{time}}", @Carbon::now(), $messages);
       

        if($gateway->type == "smtp") {
            
            $response = SendMail::sendSMTPMail($email_to, $emailTemplate->template_data['subject'], $messages, $gateway);
        }
        elseif($gateway->type == "mailjet") {

            $response = SendMail::sendMailJetMail($email_to, $emailTemplate->template_data['subject'], $messages, $gateway); 
        }
        elseif($gateway->type == "aws") {
            
            $response = SendMail::sendSesMail($email_to, $emailTemplate->template_data['subject'], $messages, $gateway); 
        }
        elseif($gateway->type == "mailgun") {
            
            $response = SendMail::sendMailGunMail($email_to, $emailTemplate->template_data['subject'], $messages, $gateway); 
        }
        elseif($gateway->type === "sendgrid") {
           
            $response = SendMail::sendGrid($gateway->address, $gateway->name, $email_to, $emailTemplate->template_data['subject'], $messages, @$gateway->mail_gateways->secret_key);
        }

        if ($response==null) {

            $data = json_encode([
                'address' => $email_to,
                'status'  => true,
            ]);
        }
        else{
            $data = json_encode([
                'address' => $gateway->name, 
                'status'  => false,
            ]);
        }

        return $data;
    }

    public function assignGateway($gateway_id, $meta_data, $user_id = null) {

        $status  = 'error';
        $message = "something went wrong";
       
        if ($gateway_id == -1) {
            
            if($user_id) {

                $user = User::where("id", $user_id)->first();
                if($user) {
                    
                    $plan_access = (object) planAccess($user);
                    if($plan_access->type == StatusEnum::FALSE->status()) {
                        
                        $gateway = Gateway::where("user_id", $user_id)->where('is_default', StatusEnum::TRUE->status())->whereNotNull('mail_gateways')->first();
                    } else {

                        $gateway = Gateway::where('is_default', StatusEnum::TRUE->status())->whereNull("user_id")->whereNotNull('mail_gateways')->first();
                    }
            
                }
            } else {

                $gateway = Gateway::where('is_default', StatusEnum::TRUE->status())->whereNull("user_id")->whereNotNull('mail_gateways')->first();
            }
            
            
            if($gateway) {

                $status = 'success';
                $message = translate("Email request are assigned with the gateway");
                $gatewayName = $gateway->name;
                $gatewayId = $gateway->id;
                $gatewayType = transformToCamelCase($gateway->type);
                if($this->hasNestedArray($meta_data)) {

                    foreach ($meta_data as &$contact) {
                    
                        $contact['gateway'] = $gatewayType;
                        $contact['gateway_id'] = $gatewayId;
                        $contact['gateway_name'] = $gatewayName;
                    }
                } else {

                    $meta_data['gateway'] = $gatewayType;
                    $contact['gateway_id'] = $gatewayId;
                    $meta_data['gateway_name'] = $gatewayName;
                }
            } else {
                $status = 'error';
                $message = translate("There are no default gateways");
            }
            
            
        } elseif ($gateway_id == 0) {
            
            $status = 'success';
            $message = translate("Email request are assigned with the gateway");
            $gateways = [];
            
            if($this->hasNestedArray($meta_data)) {
                $gateways = [];

                if ($user_id) {
                    
                    $user = User::find($user_id);
                    if ($user) {
                        $plan_access = (object) planAccess($user);
                        if ($plan_access->type == StatusEnum::FALSE->status()) {
                            $gateways = Gateway::where("user_id", $user_id)
                                ->where('status', StatusEnum::TRUE->status())
                                ->whereNotNull('mail_gateways')
                                ->inRandomOrder()
                                ->get();
                        } else {
                            $gateways = Gateway::where('status', StatusEnum::TRUE->status())
                                ->whereNull("user_id")
                                ->whereNotNull('mail_gateways')
                                ->inRandomOrder()
                                ->get();
                        }
                    }
                } else {
                    $gateways = Gateway::where('status', StatusEnum::TRUE->status())
                        ->whereNull("user_id")
                        ->whereNotNull('mail_gateways')
                        ->inRandomOrder()
                        ->get();
                }
            
                if ($gateways->isEmpty()) {
                    $status = 'error';
                    $message = translate("There are no active gateways");
                } else {
                    $status = 'success';
                    $message = translate("Email request are assigned with the gateway");
                
                    $gatewayCount = $gateways->count();
                    $gatewayIndex = 0;
                
                    foreach ($meta_data as &$contact) {
                        $gateway = $gateways[$gatewayIndex];
                        $gatewayId = $gateway->id;
                        $gatewayName = $gateway->name;
                        $gatewayType = transformToCamelCase($gateway->type);
                
                        if ($this->hasNestedArray($contact)) {
                            foreach ($contact as &$sub_contact) {
                                $sub_contact['gateway'] = $gatewayType;
                                $sub_contact['gateway_id'] = $gatewayId;
                                $sub_contact['gateway_name'] = $gatewayName;
                            }
                        } else {
                            $contact['gateway'] = $gatewayType;
                            $contact['gateway_id'] = $gatewayId;
                            $contact['gateway_name'] = $gatewayName;
                        }
                
                        $gatewayIndex = ($gatewayIndex + 1) % $gatewayCount;
                    }
                }
                
            } else {

                if($user_id) {

                    $user = User::where("id", $user_id)->first();
                    if($user) {
    
                        $plan_access = (object) planAccess($user);
                        if($plan_access->type == StatusEnum::FALSE->status()) {
    
                            $gateway = Gateway::where("user_id", $user_id)->where('status', StatusEnum::TRUE->status())->whereNotNull('mail_gateways')->inRandomOrder()->first();
                        } else {
    
                            $gateway = Gateway::where('status', StatusEnum::TRUE->status())->whereNull("user_id")->whereNotNull('mail_gateways')->inRandomOrder()->first();
                        }
                
                    }
                } else {
    
                    $gateway = Gateway::where('status', StatusEnum::TRUE->status())->whereNull("user_id")->whereNotNull('mail_gateways')->inRandomOrder()->first();
                }
                if($gateway) {

                    $status = 'success';
                    $message = translate("Email request are assigned with the gateway");
                    $gatewayId = $gateway->id;
                    $gatewayName = $gateway->name;
                    $gatewayType = transformToCamelCase($gateway->type);
                   
                    if($this->hasNestedArray($meta_data)) {
    
                        foreach ($meta_data as &$contact) {
                        
                            $contact['gateway'] = $gatewayType;
                            $contact['gateway_id'] = $gatewayId;
                            $contact['gateway_name'] = $gatewayName;
                        }
                    } else {
    
                        $meta_data['gateway'] = $gatewayType;
                        $contact['gateway_id'] = $gatewayId;
                        $meta_data['gateway_name'] = $gatewayName;
                    }
                } else {
                    $status = 'error';
                    $message = translate("There are no active gateways");
                }
            }
        } else {
           
            if($user_id) {

                $user = User::where("id", $user_id)->first();
                if($user) {

                    $plan_access = (object) planAccess($user);
                    if($plan_access->type == StatusEnum::FALSE->status()) {

                        $gateway = Gateway::where("user_id", $user_id)->where('id', $gateway_id)->whereNotNull('mail_gateways')->first();
                    } else {

                        $gateway = Gateway::where('id', $gateway_id)->whereNull("user_id")->whereNotNull('mail_gateways')->first();
                    }
            
                }
            } else {

                $gateway = Gateway::where('id', $gateway_id)->whereNull("user_id")->whereNotNull('mail_gateways')->first();
            }
            
            $gatewayId = $gateway->id;
          
            if($gateway) {
                $status = 'success';
                $message = translate("Email request are assigned with the gateway");
                $gatewayName = $gateway->name;
                $gatewayType = transformToCamelCase($gateway->type);
                if($this->hasNestedArray($meta_data)) {
    
                    foreach ($meta_data as &$contact) {
                    
                        $contact['gateway'] = $gatewayType;
                        $contact['gateway_id'] = $gatewayId;
                        $contact['gateway_name'] = $gatewayName;
                    }
                } else {
    
                    $meta_data['gateway'] = $gatewayType;
                    $contact['gateway_id'] = $gatewayId;
                    $meta_data['gateway_name'] = $gatewayName;
                }
            } else {
                $status = 'error';
                $message = translate("Selected gateway doesnt exist");
            }
           
        }
        return [$status, $message, $meta_data, $gateway ? $gateway : null];
    }

    function hasNestedArray(array $array): bool {
        
        foreach ($array as $value) {
            if (is_array($value)) {
                return true; 
            }
        }
        return false; 
    }
}
