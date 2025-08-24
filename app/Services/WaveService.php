<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WaveService
{
    protected $apiKey;
    protected $base;
    protected $successUrl;
    protected $errorUrl;

    public function __construct()
    {
        $this->apiKey     = config('services.wave.api_key');
        $this->base       = rtrim(config('services.wave.base'), '/');
        $this->successUrl = config('services.wave.success_url');
        $this->errorUrl   = config('services.wave.error_url');
    }

    /**
     *  une session de paiement Wave et le renvoie la réponse API.
     *
     * @param  int|string  $amount Montant en XOF 
     * @param  string|null $clientReference Référence interne optionnelle
     * @return array
     */
    public function createCheckoutSession($amount, $clientReference = null)
    {
        $payload = [
            'amount'      => (string) $amount,
            'currency'    => 'XOF', // devise CFA
            'success_url' => $this->successUrl,
            'error_url'   => $this->errorUrl,
        ];

        if ($clientReference) {
            $payload['client_reference'] = $clientReference;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post($this->base . '/v1/checkout/sessions', $payload);

        if ($response->failed()) {
            throw new \Exception('Erreur API Wave : ' . $response->body());
        }

        return $response->json(); // contient wave_launch_url, id, etc.
}
}