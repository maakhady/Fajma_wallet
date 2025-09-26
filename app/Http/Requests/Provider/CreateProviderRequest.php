<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;

class CreateProviderRequest extends FormRequest
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
    // app/Http/Requests/Provider/CreateProviderRequest.php
public function rules(): array
{
    return [
        'structure_name'   => 'required|string|max:255',
        'address'          => 'required|string|max:255',
        'phone'            => 'nullable|string|max:20',
        'email'            => 'nullable|email|max:255',
        'provider_type'    => 'required|in:prestataire_sante,service_finance',
        'status'           => 'required|in:active,inactive,pending',
        // ✅ Accepter un FICHIER image (jpeg/png/jpg/gif/svg/webp) jusqu'à 4 Mo
        'logo'             => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        // ✅ OU une image base64 optionnelle (data:image/...;base64,XXXX)
        'logo_base64'      => ['nullable','string','regex:/^data:image\/(png|jpe?g|gif|svg\+xml|webp);base64,[A-Za-z0-9+\/=]+$/'],
        'description'      => 'nullable|string',
        'commission_rate'  => 'nullable|numeric|min:0|max:100',
        'user_id'          => 'nullable|exists:users,id'
    ];
}

public function messages(): array
{
    return [
        'structure_name.required' => 'Le nom de la structure est obligatoire',
        'address.required'        => 'L\'adresse est obligatoire',
        'provider_type.required'  => 'Le type de prestataire est obligatoire',
        'provider_type.in'        => 'Le type de prestataire doit être prestataire_sante ou service_finance',
        'status.in'               => 'Le statut doit être active, inactive ou pending',
        'logo.file'               => 'Le logo doit être un fichier',
        'logo.mimes'              => 'Formats autorisés: jpeg, png, jpg, gif, svg, webp',
        'logo.max'                => 'Le logo ne doit pas dépasser 4 Mo',
        'logo_base64.regex'       => 'Le logo_base64 doit être une image base64 valide (png/jpeg/gif/svg/webp)',
        'commission_rate.numeric' => 'Le taux de commission doit être un nombre',
        'commission_rate.min'     => 'Le taux de commission doit être positif',
        'commission_rate.max'     => 'Le taux de commission ne peut pas dépasser 100%',
        'user_id.exists'          => 'L\'utilisateur sélectionné n\'existe pas'
    ];
}

}
