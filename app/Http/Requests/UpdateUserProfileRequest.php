<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserProfileRequest extends FormRequest
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
        $userId = Auth::guard('api')->id();

        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|email|max:255|unique:users,contact_email,' . $userId,
            'phone' => 'sometimes|nullable|string|max:20|unique:users,phone,' . $userId,
            'profile_photo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
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
            'contact_email.email' => 'Veuillez entrer une adresse e-mail de contact valide',
            'contact_email.unique' => 'Cette adresse e-mail de contact est déjà utilisée',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
            'profile_photo.image' => 'Le fichier doit être une image',
            'profile_photo.max' => 'L\'image ne doit pas dépasser 2Mo',
        ];
    }
}
