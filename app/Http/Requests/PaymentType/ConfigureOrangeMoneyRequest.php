<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureOrangeMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Optionnel: restreindre aux admins
        // return auth()->check() && auth()->user()->role === 'admin';
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normaliser l'environnement (prod -> production)
        $env = $this->input('environment');
        if (is_string($env)) {
            $env = strtolower($env);
            if ($env === 'prod') $env = 'production';
            if ($env === 'dev' || $env === 'sandbox') $env = 'sandbox';
            $this->merge(['environment' => $env]);
        }

        // Accepter callback_url comme alias et le ranger en callback_url (camelCase stockable si tu veux)
        if ($this->has('callbackUrl') && !$this->has('callback_url')) {
            $this->merge(['callback_url' => $this->input('callbackUrl')]);
        }
    }

    public function rules(): array
    {
        return [
            'api_key'       => ['bail','required','string','max:255'],
            'api_secret'    => ['bail','required','string','max:255'],
            'merchant_id'   => ['bail','required','string','max:100'],
            'merchant_name' => ['bail','required','string','max:150'],

            // "sandbox" ou "production" (tu utilises "sandbox" dans le service)
            'environment'   => ['bail','required','in:sandbox,production'],

            // Optionnel mais utile (ta config['wallet_type'] est lue par le service)
            'wallet_type'   => ['nullable','in:MSISDN,OM,ORANGE'],

            // URLs optionnelles
            'notif_url'     => ['nullable','url','max:255'],
            'return_url'    => ['nullable','url','max:255'],
            'cancel_url'    => ['nullable','url','max:255'],
            'callback_url'  => ['nullable','url','max:255'],

            // Autres champs de PaymentType (si tu les passes dans le même endpoint)
            'display_name'  => ['sometimes','string','max:100'],
            'icon'          => ['sometimes','nullable','string','max:255'],
            'description'   => ['sometimes','nullable','string'],
            'is_active'     => ['sometimes','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'api_key.required'        => 'La clé API Orange Money est obligatoire.',
            'api_secret.required'     => 'La clé secrète Orange Money est obligatoire.',
            'merchant_id.required'    => 'L’identifiant marchand Orange Money est obligatoire.',
            'merchant_name.required'  => 'Le nom marchand Orange Money est obligatoire.',
            'environment.required'    => 'L’environnement Orange Money est obligatoire.',
            'environment.in'          => 'L’environnement doit être "sandbox" ou "production".',
            'wallet_type.in'          => 'Le wallet_type doit être "MSISDN", "OM" ou "ORANGE".',
            'notif_url.url'           => 'La notif_url doit être une URL valide.',
            'return_url.url'          => 'La return_url doit être une URL valide.',
            'cancel_url.url'          => 'La cancel_url doit être une URL valide.',
            'callback_url.url'        => 'La callback_url doit être une URL valide.',
        ];
    }

    public function attributes(): array
    {
        return [
            'api_key'       => 'clé API',
            'api_secret'    => 'clé secrète',
            'merchant_id'   => 'code marchand',
            'merchant_name' => 'nom marchand',
            'wallet_type'   => 'type de wallet',
            'notif_url'     => 'URL de notification',
            'return_url'    => 'URL de retour',
            'cancel_url'    => 'URL d’annulation',
            'callback_url'  => 'URL de callback',
        ];
    }
}
