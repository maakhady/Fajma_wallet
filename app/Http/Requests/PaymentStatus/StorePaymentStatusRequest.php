<?php

namespace App\Http\Requests\PaymentStatus;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // La vérification d'autorisation est déjà gérée par le middleware dans le contrôleur
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
            'name' => 'required|string|max:255|unique:payment_status',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20'
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
            'name.required' => 'Le nom du statut est requis.',
            'name.unique' => 'Ce nom de statut est déjà utilisé.',
            'display_name.required' => 'Le nom d\'affichage est requis.'
        ];
    }
}