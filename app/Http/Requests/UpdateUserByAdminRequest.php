<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserByAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // L'autorisation sera gérée dans le contrôleur
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $userId,
            'contact_email' => 'sometimes|email|max:255|unique:users,contact_email,' . $userId,
            'phone' => 'sometimes|nullable|string|max:20|unique:users,phone,' . $userId,
            'profile_photo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:patient,admin,medecin,prestataire',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'first_name.string' => 'Le prénom doit être une chaîne de caractères',
            'last_name.string' => 'Le nom doit être une chaîne de caractères',
            'email.email' => 'Veuillez entrer une adresse e-mail valide',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée',
            'contact_email.email' => 'Veuillez entrer une adresse e-mail de contact valide',
            'contact_email.unique' => 'Cette adresse e-mail de contact est déjà utilisée',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
            'password.min' => 'Le mot de passe doit comporter au moins 8 caractères',
            'role.in' => 'Le rôle sélectionné n\'est pas valide',
            'profile_photo.image' => 'Le fichier doit être une image',
            'profile_photo.max' => 'L\'image ne doit pas dépasser 2Mo',
            'is_active.boolean' => 'Le statut d\'activation doit être vrai ou faux',
        ];
    }
}
