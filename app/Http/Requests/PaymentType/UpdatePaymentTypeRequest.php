<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Autorisation gérée par le contrôleur
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|unique:payment_types,name,' . $this->route('id'),
            'display_name' => 'sometimes|string',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'is_active' => 'boolean',
            'config' => 'nullable|array',
            'config.*' => 'nullable'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ce nom de type de paiement existe déjà',
            'icon.image' => 'Le fichier doit être une image',
            'icon.mimes' => 'Le format de l\'image doit être jpeg, png, jpg ou svg',
            'icon.max' => 'L\'image ne doit pas dépasser 2Mo'
        ];
    }
}
