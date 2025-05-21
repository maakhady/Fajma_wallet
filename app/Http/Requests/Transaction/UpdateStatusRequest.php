<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Cette requête n'est autorisée que pour les administrateurs
        // La vérification sera effectuée dans le middleware 'role:admin'
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
            'payment_status_id' => 'required|integer|exists:payment_status,id',
            'reason' => 'required|string|min:5|max:255',
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
            'payment_status_id.exists' => 'Ce statut de paiement n\'existe pas',
            'reason.min' => 'La raison doit contenir au moins 5 caractères',
        ];
    }
}