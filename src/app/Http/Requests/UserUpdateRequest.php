<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        
        $rules = [
            'email'   => ['nullable','unique:users,email,'.request()->id],
            'name'    => 'nullable|max:120',
            'address' => 'nullable|max:250',
            'city'    => 'nullable|max:250',
            'state'   => 'nullable|max:250',
            'zip'     => 'nullable|max:250',
            'status'  => 'nullable|in:1,0',
        ];

        return $rules;
    }
}
