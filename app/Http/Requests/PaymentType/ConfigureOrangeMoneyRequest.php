<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureOrangeMoneyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Autorisation gérée par le contrôleur
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'merchant_id' => 'required|string',
            'environment' => 'required|in:sandbox,production'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'api_key.required' => 'La clé API Orange Money est obligatoire',
            'api_secret.required' => 'La clé secrète Orange Money est obligatoire',
            'merchant_id.required' => 'L\'identifiant marchand Orange Money est obligatoire',
            'environment.required' => 'L\'environnement Orange Money est obligatoire',
            'environment.in' => 'L\'environnement doit être sandbox ou production'
        ];
    }
}
