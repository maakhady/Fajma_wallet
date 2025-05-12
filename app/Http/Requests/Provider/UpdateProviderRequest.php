<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // L'autorisation est gérée par le contrôleur
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'structure_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'provider_type' => 'sometimes|in:prestataire_sante,service_finance',
            'status' => 'sometimes|in:active,inactive,pending',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'user_id' => 'nullable|exists:users,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'structure_name.string' => 'Le nom de la structure doit être une chaîne de caractères',
            'address.string' => 'L\'adresse doit être une chaîne de caractères',
            'provider_type.in' => 'Le type de prestataire doit être prestataire_sante ou service_finance',
            'status.in' => 'Le statut doit être active, inactive ou pending',
            'logo.image' => 'Le fichier doit être une image',
            'logo.mimes' => 'L\'image doit être au format jpeg, png, jpg, gif ou svg',
            'logo.max' => 'L\'image ne doit pas dépasser 2Mo',
            'commission_rate.numeric' => 'Le taux de commission doit être un nombre',
            'commission_rate.min' => 'Le taux de commission doit être positif',
            'commission_rate.max' => 'Le taux de commission ne peut pas dépasser 100%',
            'user_id.exists' => 'L\'utilisateur sélectionné n\'existe pas'
        ];
    }
}
