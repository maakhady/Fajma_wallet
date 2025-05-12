<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigRequest extends FormRequest
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
            'config' => 'required|array',
            'config.api_key' => 'sometimes|string',
            'config.api_secret' => 'sometimes|string',
            'config.merchant_id' => 'sometimes|string',
            'config.environment' => 'sometimes|string|in:sandbox,production',
            'config.*' => 'nullable'
        ];
    }
}
