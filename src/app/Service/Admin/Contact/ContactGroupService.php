<?php

namespace App\Service\Admin\Contact;

use App\Enums\ContactAttributeEnum;
use App\Models\Group;
use App\Models\Contact;
use App\Enums\StatusEnum;
use Carbon\Carbon;

class ContactGroupService
{ 
    public function getAllGroups($user_id = null) {

        return Group::search(['name'])
                        ->where("user_id", $user_id)
                        ->filter(['status'])
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());
    }

    public function getGroupWithChild($id, $user_id = null) {
        
        return Group::search(['name'])
                        ->where('id', $id)
                        ->where("user_id", $user_id)
                        ->filter(['status'])
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());
    }

    public function statusUpdate($request) {
        
        try {
            $status  = true;
            $reload  = false;
            $message = translate('Group status updated successfully');
            $group = Group::where("id",$request->input('id'))->first();
            $column  = $request->input("column");
            
            if($request->value == StatusEnum::TRUE->status()) {
                
                $group->status = StatusEnum::FALSE->status();
                $group->update();
            } else {

                $group->status = StatusEnum::TRUE->status();
                $group->update();
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

    public function updateOrCreate($data, $user_id = null) {

        if($user_id) {

            $data['user_id'] = $user_id;
        }
        
        Group::updateOrCreate([
                    
            "uid" => $data["uid"] ?? null

        ], $data);
    }


    /**
     * 
     * @param string $uid
     *
     * @return Group $group
     */
    public function fetchWithUid(string $uid) {

       return Group::where("uid", $uid)->first();
    }

    /**
     * 
     * @param string $id
     *
     * @return Group $group
     */
    public function fetchWithId(string $uid) {

       return Group::where("uid", $uid)->first();
    }

    /**
     * 
     * @param string $uid
     *
     * @return array
     */
    public function deleteGroup(string $uid): array {

        $group = $this->fetchWithUid($uid);
        if($group) {
            $group->delete();
            Contact::whereNull('user_id')->where('group_id', $group->id)->delete();
            $status   = 'success';
            $message = translate("Group ").$group->name.translate(' has been deleted successfully from admin panel');
        } else {

            $status  = 'error';
            $message = translate("Group couldn't be found"); 
        }
        return [
            $status, 
            $message
        ];
    }

    public function retrieveContacts($type, $contact_groups, $group_logic = null, $meta_name = null, $logic = null, $logic_range = null, $user_id = null) {

        $meta_data = [];
        $contact = Contact::query();
        $contact->whereIn('group_id', $contact_groups);

        if ($group_logic) {
        
            if (strpos($meta_name, "::") !== false) {

                $attributeParts = explode("::", $meta_name);
                $attributeType  = $attributeParts[1];
                
                if ($attributeType == ContactAttributeEnum::DATE->value) {

                    $startDate = Carbon::parse($logic);
        
                    if ($logic_range) {

                        $endDate = Carbon::parse($logic_range);
                        $contact = $contact->get()->filter(function ($contact) use ($startDate, $endDate, $attributeParts) {

                            $attr = Carbon::parse($contact->attributes->{$attributeParts[0]}->value);
                            return $attr->between($startDate, $endDate);
                        });
                    } else {

                        $contact = $contact->get()->filter(function ($contact) use ($startDate, $attributeParts) {

                            $attr = Carbon::parse($contact->attributes->{$attributeParts[0]}->value);
                            return $attr->isSameDay($startDate);
                        });
                    }
                } elseif ($attributeType == ContactAttributeEnum::BOOLEAN->value) {

                    $logicValue = filter_var($logic, FILTER_VALIDATE_BOOLEAN);
                    $contact    = $contact->get()->filter(function ($contact) use ($attributeParts, $logicValue) {

                        $attrValue = filter_var($contact->attributes->{$attributeParts[0]}->value, FILTER_VALIDATE_BOOLEAN);
                        return $attrValue === $logicValue;
                    });

                } elseif ($attributeType == ContactAttributeEnum::NUMBER->value) { 

                    $numericLogic = filter_var($logic, FILTER_VALIDATE_FLOAT);
                
                    if ($logic_range) {

                        $numericRange = filter_var($logic_range, FILTER_VALIDATE_FLOAT);
                        $contact      = $contact->get()->filter(function ($contact) use ($attributeParts, $numericLogic, $numericRange) {

                            $attrValue = filter_var($contact->attributes->{$attributeParts[0]}->value, FILTER_VALIDATE_FLOAT);
                            return $attrValue >= $numericLogic && $attrValue <= $numericRange;
                        });
                    } else {

                        $contact = $contact->get()->filter(function ($contact) use ($attributeParts, $numericLogic) {

                            $attrValue = filter_var($contact->attributes->{$attributeParts[0]}->value, FILTER_VALIDATE_FLOAT);
                            return $attrValue == $numericLogic;
                        });
                    }
                } elseif ($attributeType == ContactAttributeEnum::TEXT->value) { 

                    $textLogic = $logic;
                    $contact   = $contact->get()->filter(function ($contact) use ($attributeParts, $textLogic) {

                        $attrValue = $contact->attributes->{$attributeParts[0]}->value;
                        return stripos($attrValue, $textLogic) !== false;
                    });
                }
            } else {
                $contact->where($meta_name, 'like', "%$logic%");
            }

            
        }
        
        if (!is_null($user_id)) {

            $contact->where('user_id', $user_id);
        } else {

            $contact->whereNull('user_id');
        }
        if ($type) {

            $allContactNumber[] = $contact->pluck("$type".'_contact')->toArray();
            $numberGroupName    = $contact->pluck('first_name', "$type".'_contact')->toArray();
            $contact_ids        = $contact->pluck('id', "$type".'_contact')->toArray();

          

            foreach ($allContactNumber[0] as $number) {
                
                $meta_data[] = [

                    'contact' => $number,
                    'first_name'  => $numberGroupName[$number] ?? null,
                    'id' => $contact_ids[$number] ?? null
                ];
            }

            
        }

        return $meta_data;
    }
}