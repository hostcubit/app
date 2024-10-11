<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

class ContactGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $rules = [
            
            "name" => "required",
        ];
        if(request()->routeIs("admin.contact.group.update")){
            $rules ['uid'] = "required|exists:groups,uid";
        }
        return $rules;
    }
}
