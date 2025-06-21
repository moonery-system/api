<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // 'delivery_status_id' => 'required|integer',
            'client_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.name' => 'required|min:5|max:255',
            'items.*.quantity' => 'required|integer',
            'items.*.weight' => 'required'
        ];
    }
}
