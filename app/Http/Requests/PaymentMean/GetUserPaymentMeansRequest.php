<?php

namespace App\Http\Requests\PaymentMean;

use Illuminate\Foundation\Http\FormRequest;

class GetUserPaymentMeansRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // L'authentification est gérée par le middleware
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
            'active_only' => 'sometimes|in:true,false',
            'user_id' => 'sometimes|exists:users,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'active_only.in' => 'La valeur de active_only doit être "true" ou "false".',
            'user_id.exists' => 'L\'utilisateur sélectionné n\'existe pas.'
        ];
    }
}
