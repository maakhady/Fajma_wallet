<?php

namespace App\Http\Requests\PaymentMean;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePaymentMeanRequest extends FormRequest
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
            'payment_type_id' => 'required|exists:payment_types,id',
            'account_identifier' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'user_id' => 'sometimes|exists:users,id',
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
            'payment_type_id.required' => 'Le type de paiement est requis.',
            'payment_type_id.exists' => 'Le type de paiement sélectionné n\'existe pas.',
            'account_identifier.required' => 'L\'identifiant du compte est requis.',
            'status.required' => 'Le statut du moyen de paiement est requis.',
            'status.in' => 'Le statut doit être "active" ou "inactive".',
            'user_id.exists' => 'L\'utilisateur sélectionné n\'existe pas.',
            'is_default.boolean' => 'La valeur pour "par défaut" doit être un booléen.',
            'metadata.array' => 'Les métadonnées doivent être un tableau.'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $user = Auth::user();
        
        // Si l'utilisateur n'est pas admin, ou si aucun user_id n'est fourni, utiliser l'ID de l'utilisateur connecté
        if (!$user || $user->role !== 'admin' || !$this->has('user_id')) {
            $this->merge([
                'user_id' => $user->id,
            ]);
        }
    }
}