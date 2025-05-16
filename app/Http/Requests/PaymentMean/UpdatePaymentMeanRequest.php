<?php

namespace App\Http\Requests\PaymentMean;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMeanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // L'autorisation sera gérée dans le contrôleur
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
            'payment_type_id' => 'sometimes|exists:payment_types,id',
            'account_identifier' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'is_default' => 'sometimes|boolean',
            'metadata' => 'sometimes|array'
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
            'payment_type_id.exists' => 'Le type de paiement sélectionné n\'existe pas.',
            'status.in' => 'Le statut doit être "active" ou "inactive".',
            'is_default.boolean' => 'La valeur pour "par défaut" doit être un booléen.',
            'metadata.array' => 'Les métadonnées doivent être un tableau.'
        ];
    }
}
