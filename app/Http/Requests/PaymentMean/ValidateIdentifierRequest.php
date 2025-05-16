<?php

namespace App\Http\Requests\PaymentMean;

use Illuminate\Foundation\Http\FormRequest;

class ValidateIdentifierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Tout utilisateur authentifié peut valider un identifiant
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
            'account_identifier' => 'required|string|max:255',
            'payment_type_id' => 'required|exists:payment_types,id'
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
            'account_identifier.required' => 'L\'identifiant du compte est requis.',
            'payment_type_id.required' => 'Le type de paiement est requis.',
            'payment_type_id.exists' => 'Le type de paiement sélectionné n\'existe pas.'
        ];
    }
}
