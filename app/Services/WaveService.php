<?php
// app/Services/WaveService.php - VERSION AMÉLIORÉE

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaveService
{
    protected $apiKey;
    protected $base;
    protected $successUrl;
    protected $errorUrl;
    protected $enabled;

    public function __construct()
    {
        $this->apiKey = config('services.wave.api_key');
        $this->base = rtrim(config('services.wave.base'), '/');
        $this->successUrl = config('services.wave.success_url');
        $this->errorUrl = config('services.wave.error_url');
        $this->enabled = config('services.wave.enabled', false);
        
        // Validation de la configuration
        $this->validateConfiguration();
    }

    /**
     * Valide la configuration Wave
     */
    private function validateConfiguration(): void
    {
        if (!$this->enabled) {
            Log::warning('Wave payment est désactivé dans la configuration');
            return;
        }

        if (empty($this->apiKey)) {
            throw new \Exception('WAVE_API_KEY n\'est pas configuré dans .env');
        }

        if (empty($this->base)) {
            throw new \Exception('WAVE_BASE_URL n\'est pas configuré dans .env');
        }
    }

    /**
     * Crée une session de paiement Wave
     *
     * @param int|string $amount Montant en XOF
     * @param string|null $clientReference Référence interne
     * @param array $metadata Métadonnées additionnelles
     * @return array
     * @throws \Exception
     */
    public function createCheckoutSession($amount, $clientReference = null, array $metadata = [])
    {
        if (!$this->enabled) {
            throw new \Exception('Wave payment est désactivé');
        }

        try {
            $payload = [
                'amount' => (string) $amount,
                'currency' => 'XOF',
                'success_url' => $this->successUrl,
                'error_url' => $this->errorUrl,
            ];

            if ($clientReference) {
                $payload['client_reference'] = $clientReference;
            }

            // Ajouter métadonnées si fournies
            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            Log::info('🌊 Création session Wave', [
                'amount' => $amount,
                'reference' => $clientReference
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->base . '/v1/checkout/sessions', $payload);

            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error('❌ Erreur API Wave', [
                    'status' => $response->status(),
                    'body' => $errorBody
                ]);
                
                throw new \Exception('Erreur API Wave : ' . $errorBody);
            }

            $result = $response->json();

            Log::info('✅ Session Wave créée', [
                'checkout_id' => $result['id'] ?? null,
                'wave_launch_url' => $result['wave_launch_url'] ?? null
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('❌ Exception Wave createCheckoutSession: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifie le statut d'une session Wave
     *
     * @param string $checkoutId
     * @return array
     * @throws \Exception
     */
    public function getCheckoutSession(string $checkoutId)
    {
        if (!$this->enabled) {
            throw new \Exception('Wave payment est désactivé');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->base . '/v1/checkout/sessions/' . $checkoutId);

            if ($response->failed()) {
                throw new \Exception('Erreur lors de la récupération de la session Wave');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('❌ Exception Wave getCheckoutSession: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifie si Wave est activé
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }
}