<?php

namespace App\Service\Admin\Contact;

use App\Enums\StatusEnum;
use App\Models\User;

class ContactSettingsService
{ 
    public function save($data, $user_id = null) {
        if ($user_id) {
            $user = User::where("id", $user_id)->first();
        }
        
        $new_attribute_name = strtolower(str_replace(' ', '_', $data["attribute_name"]));
        
        $new_data['contact_meta_data'] = [
            $new_attribute_name => [
                "type"   => (int)$data["attribute_type"],
                "status" => StatusEnum::TRUE->status(),
            ]
        ];
        
        $old_data = $user_id ? json_decode($user->contact_meta_data, true) : json_decode(site_settings('contact_meta_data'), true);
    
        if (isset($data["old_attribute_name"])) {
            $old_attribute_name = strtolower(str_replace(' ', '_', $data["old_attribute_name"]));
            
            if (isset($old_data[$old_attribute_name])) {
                $old_data[$new_attribute_name] = array_merge($old_data[$old_attribute_name], $new_data['contact_meta_data'][$new_attribute_name]);
                
                if ($old_attribute_name !== $new_attribute_name) {
                    unset($old_data[$old_attribute_name]);
                }
            }
        } else {
            
            $old_data[$new_attribute_name] = $new_data['contact_meta_data'][$new_attribute_name];
        }
    
        $final_data['contact_meta_data'] = $old_data;
        return $final_data;
    }
    
    

    public function delete($data, $user = null) {

        $attribute_name = strtolower(str_replace(' ', '_', $data["attribute_name"]));
        $old_data       = $user ? json_decode($user->contact_meta_data, true) : json_decode(site_settings('contact_meta_data'), true);
        unset($old_data[$attribute_name]);
        $final_data['contact_meta_data'] = $old_data;
        return $final_data;
    }
}