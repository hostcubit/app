<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CommunicationRequest extends FormRequest
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
            'schedule_at' => 'nullable|date_format:Y-m-d H:i',
            'gateway_id'  => 'required',
            'contacts'    => 'required',
        ];

        if(request()->type == 'sms') {

            $rules['message.message_body'] = ['required'];
        }
        if(request()->type == 'email') {

            $rules['message.subject']      = ['required'];
            $rules['message.message_body'] = ['required'];
        }
        
        if(request()->type == 'whatsapp') {

            $rules['message.message_body'] = ['required_if:method,without_cloud_api'];
            $rules['whatsapp_template_id'] = ['required_if:method,cloud_api'];
        }
        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        $messages = [
            'message.required'                  => translate('The message data is required.'),
            'gateway_id.required'               => translate('Gateway must be selected'),
            'schedule_at.date_format'           => translate('The :attribute must be in the format "YYYY-MM-DD HH:MM".'),
            'meta_data.contact_number.required' => translate('The recipient contact details are required'),
        ];
        if(request()->type == 'sms') {

            $messages['message.message_body.required'] = translate('Message body is required.');
        }
        if(request()->type == 'email') {

            $messages['message.subject.required']      = translate('Email subject is required.');
            $messages['message.message_body.required'] = translate('The Email body is required');
        }
        
        return $messages;
    }
}
