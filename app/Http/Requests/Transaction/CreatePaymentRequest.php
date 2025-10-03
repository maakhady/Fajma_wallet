<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreatePaymentRequest extends FormRequest
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
            'card_id' => [
                'required',
                'integer',
                'exists:cards,id,user_id,' . Auth::id(), // Vérifier que la carte appartient à l'utilisateur
            ],
            'amount' => 'required|numeric|min:100', // Montant minimum (ex: 100 FCFA)
            'provider_id' => 'required|integer|exists:providers,id,status,active',
            'payment_mean_id' => 'required|integer|exists:payment_means,id,user_id,' . Auth::id(), // <-- AJOUTÉ
            'verification_code' => 'required|string|size:5',
            'description' => 'sometimes|string|max:255',
            'metadata' => 'sometimes|array',
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
            'card_id.exists' => 'Cette carte ne vous appartient pas ou n\'existe pas',
            'amount.min' => 'Le montant minimum de paiement est de 100 FCFA',
            'provider_id.exists' => 'Ce prestataire n\'existe pas ou n\'est pas actif',
            'payment_mean_id.required' => 'Le moyen de paiement est requis.', // <-- AJOUTÉ
            'payment_mean_id.exists' => 'Le moyen de paiement sélectionné n\'existe pas ou ne vous appartient pas.', // <-- AJOUTÉ
            'verification_code.size' => 'Le code de vérification doit contenir exactement 5 caractères',
        ];
    } 

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true)
            ]);
        }
    }
}
