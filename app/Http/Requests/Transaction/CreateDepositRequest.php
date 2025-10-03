<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateDepositRequest extends FormRequest
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
            'payment_mean_id' => 'required|integer|exists:payment_means,id,user_id,' . Auth::id(),
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
            'amount.min' => 'Le montant minimum de dépôt est de 100 FCFA',
            'payment_mean_id.exists' => 'Ce moyen de paiement ne vous appartient pas ou n\'existe pas',
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