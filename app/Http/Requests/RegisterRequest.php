<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users|max:255',
            'phone'      => 'nullable|string|unique:users|max:20',
            'password'   => 'required|string|min:8|confirmed',
            'role'       => 'required|in:patient,admin,medecin,prestataire', // Ajout du rôle prestataire
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Validation pour la photo de profil
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
            'first_name.required' => 'Le prénom est obligatoire',
            'last_name.required'  => 'Le nom est obligatoire',
            'email.required'      => 'L\'adresse e-mail est obligatoire',
            'email.email'         => 'Veuillez entrer une adresse e-mail valide',
            'email.unique'        => 'Cette adresse e-mail est déjà utilisée',
            'phone.unique'        => 'Ce numéro de téléphone est déjà utilisé',
            'password.required'   => 'Le mot de passe est obligatoire',
            'password.min'        => 'Le mot de passe doit comporter au moins 8 caractères',
            'password.confirmed'  => 'La confirmation du mot de passe ne correspond pas',
            'role.required'       => 'Le rôle est obligatoire',
            'role.in'             => 'Le rôle sélectionné n\'est pas valide',
            'profile_photo.image' => 'Le fichier doit être une image',
            'profile_photo.max'   => 'L\'image ne doit pas dépasser 2Mo',
        ];
    }
}