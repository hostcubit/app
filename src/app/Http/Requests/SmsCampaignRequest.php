<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class SmsCampaignRequest extends FormRequest
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
        return [
            'contacts' => 'required|array',
            'name' => 'required',
            'method' => ['required', 'in:' . StatusEnum::TRUE->status() . ',' . StatusEnum::FALSE->status()],
            'schedule_at' => ['required', 'date_format:Y-m-d H:i'],
            'repeat_time' => ['required', 'integer', 'gte:0'],
            'repeat_format' => [
                'nullable',
                'required_if:repeat_time,>0'
            ],
            'sms_type' => ['required', 'in:plain,unicode'],
            'message' => 'array',
            'message.message_body' => 'required',
            'gateway_id' => 'required'
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ((int)$this->input('repeat_time') > 0 && empty($this->input('repeat_format'))) {
                $validator->errors()->add('repeat_format', 'The repeat format field is required when repeat time is greater than 0.');
            }
        });
    }
}
