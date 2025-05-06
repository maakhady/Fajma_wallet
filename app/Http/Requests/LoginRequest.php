<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Modifié de false à true pour permettre l'accès
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => 'required|email|max:255',
            'password' => 'required|string',
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
            'email.required' => 'L\'adresse e-mail est obligatoire',
            'email.email'    => 'Veuillez entrer une adresse e-mail valide',
            'password.required' => 'Le mot de passe est obligatoire',
        ];
    }
    
    /**
     * Prepare the data for validation.
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer les données avant validation
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }
}