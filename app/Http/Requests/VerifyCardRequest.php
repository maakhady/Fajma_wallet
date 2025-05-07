<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // L'autorisation sera gérée dans le contrôleur
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
            // 'card_number' => 'required|exists:cards,card_number',
            'verification_code' => 'required|size:5',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // 'card_number.required' => 'Le numéro de carte est obligatoire',
            // 'card_number.exists' => 'Ce numéro de carte n\'existe pas',
            'verification_code.required' => 'Le code de vérification est obligatoire',
            'verification_code.size' => 'Le code de vérification doit comporter exactement 5 chiffres',
        ];
    }
}
