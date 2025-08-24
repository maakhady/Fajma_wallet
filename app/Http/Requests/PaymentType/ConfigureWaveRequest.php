<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureWaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisation gérée par le contrôleur/policies
        return true;
    }

    public function rules(): array
    {
        return [
            'api_key'        => ['required', 'string'],
            'success_url'    => ['required', 'url'],
            'error_url'      => ['required', 'url'],
            'webhook_secret' => ['nullable', 'string'],   // requis seulement si tu actives un webhook
            'base_url'       => ['nullable', 'url'],      // ex: https://api.wave.com
            'environment'    => ['nullable', 'in:sandbox,production,custom'],

            // Options facultatives
            'enable_balance' => ['sometimes', 'boolean'],
            'enable_payout'  => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'api_key.required'     => 'La clé API Wave est obligatoire.',
            'api_key.string'       => 'La clé API Wave doit être une chaîne de caractères.',
            'success_url.required' => 'L’URL de succès est obligatoire.',
            'success_url.url'      => 'L’URL de succès doit être une URL valide.',
            'error_url.required'   => 'L’URL d’erreur est obligatoire.',
            'error_url.url'        => 'L’URL d’erreur doit être une URL valide.',
            'webhook_secret.string'=> 'Le secret de webhook doit être une chaîne de caractères.',
            'base_url.url'         => 'La base URL doit être une URL valide.',
            'environment.in'       => 'L’environnement doit être "sandbox", "production" ou "custom".',
            'enable_balance.boolean' => 'enable_balance doit être un booléen.',
            'enable_payout.boolean'  => 'enable_payout doit être un booléen.',
        ];
    }
}
