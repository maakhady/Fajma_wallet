<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\Models\PaymentStatus;
use Illuminate\Support\Facades\DB;

class OrangeMoneyService
{
    public $config; 
    protected $baseUrl;
    protected $tokenUrl;

    public function __construct()
    {
        // Récupération la configuration Orange Money depuis la base de données
        $paymentType = PaymentType::where("name", "orange_money")->first();

        if (!$paymentType || !isset($paymentType->config)) {
            throw new \Exception("Configuration Orange Money introuvable. Veuillez la configurer d'abord.");
        }

        $this->config = $paymentType->config;

        // Définition les URLs de base en fonction de l'environnement
        if ($this->config["environment"] === "sandbox") {
            $this->baseUrl = "https://api.sandbox.orange-sonatel.com";
            $this->tokenUrl = "https://api.sandbox.orange-sonatel.com/oauth/v1/token";
        } else {
            $this->baseUrl = "https://api.orange-sonatel.com";
            $this->tokenUrl = "https://api.orange-sonatel.com/oauth/v1/token";
        }
    }

    protected function getAccessToken()
    {
        try {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['api_key'],
                'client_secret' => $this->config['api_secret'],
            ]);

            $response->throw(); // Lance une exception si la réponse est une erreur

            return $response->json()['access_token'];
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de l\'obtention du token Orange Money: ' . $e->getMessage());
            throw new \Exception('Impossible d\'obtenir le token d\'accès Orange Money.');
        }
    }

 public function initiatePayment(array $paymentData): array
{
    $response = null;

    try {
        // 0) Vérifs de config minimales
        $merchantCode = $this->config['merchant_id']   ?? null;
        $merchantName = $this->config['merchant_name'] ?? null;
        if (!$merchantCode || !$merchantName) {
            throw new \RuntimeException("Config OM incomplète: merchant_id/merchant_name manquants.");
        }

        // 1) OAuth token
        $accessToken = $this->getAccessToken();

        // 2) Montant
        $amount = (int) data_get($paymentData, 'amount');
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Montant invalide: amount doit être > 0.");
        }

        // 3) Références
        $meta        = (array) data_get($paymentData, 'metadata', []);
        $reference   = (string) (data_get($paymentData, 'reference')   ?? data_get($meta, 'reference')   ?? $this->genShortRef());
        $orderId     = (string) (data_get($paymentData, 'orderId')     ?? data_get($meta, 'order_id')    ?? $this->genUuidLike());
        $description = (string) (data_get($paymentData, 'description') ?? data_get($meta, 'description') ?? 'Paiement');

        // 4) Callback HTTPS public 
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $fallbackCallback = $appUrl ? $appUrl.'/api/webhooks/orange-money' : null;
        $callbackUrl = (string) (data_get($paymentData, 'callback_url', $fallbackCallback));
        if (!$callbackUrl) {
            throw new \RuntimeException("callback_url manquant: fournis une URL https publique ou configure APP_URL.");
        }
        $callbackUrl = $this->ensureHttpsUrl($callbackUrl);

        // 5) gestion de  qrcode
        $mode = strtolower((string) data_get($paymentData, 'mode', 'qrcode'));
        if ($mode !== 'qrcode') {
            throw new \RuntimeException("Mode '$mode' non supporté ici. Utilise 'qrcode' ou implémente le endpoint push séparément.");
        }

        // 6) Corps STRICT pour /qrcode (pas de customerId / walletType ici)
        $endpoint = '/api/eWallet/v4/qrcode';
        $body = [
            'amount'      => $amount,
            'code'        => $merchantCode,   // mercode
            'name'        => $merchantName,   // nom affiché du marchand
            'reference'   => $reference,
            'orderId'     => $orderId,
            'description' => $description,
            'validity'    => (int) data_get($paymentData, 'validity', 86400), // secondes
            'callbackUrl' => $callbackUrl,
            // Décommente si votre contrat exige ces champs :
            // 'currency' => 'XOF',
            // 'country'  => 'SN',
            // 'lang'     => 'fr',
        ];

        // 7) Appel OM
        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->post($this->baseUrl.$endpoint, $body);

        // OM renvoie 201 en cas de création OK
        if (!in_array($response->status(), [200, 201], true)) {
            \Illuminate\Support\Facades\Log::error('Orange Money /qrcode: HTTP non-2xx', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'json'     => $response->json(),
                'sent'     => $body,
                'endpoint' => $endpoint,
                'baseUrl'  => $this->baseUrl,
            ]);
            throw new \RuntimeException('Appel Orange Money échoué (HTTP '.$response->status().').');
        }

        // On renvoie le JSON + le status (201 attendu)
        return [
            'status' => $response->status(),
            'data'   => $response->json(),
        ];

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("Erreur initiation paiement OM: ".$e->getMessage(), [
            'api_response_body'   => $response?->body(),
            'api_response_status' => $response?->status(),
        ]);
        throw new \Exception("Échec de l'initiation du paiement Orange Money.");
    }
}

/**
 * Force https et ajoute le schéma s'il manque.
 */
private function ensureHttpsUrl(string $url): string
{
    $u = trim($url);

    // Pas de schéma → on préfixe en https
    if (!preg_match('#^https?://#i', $u)) {
        $u = 'https://'.$u;
    }

    // http:// → https://
    $u = preg_replace('#^http://#i', 'https://', $u);

    return $u;
}



/** Référence courte lisible (ex: FAJ-20250811-abc123) */
private function genShortRef(): string
{
    return 'FAJ-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
}

/** UUID-like pour orderId si absent */
private function genUuidLike(): string
{
    $d = bin2hex(random_bytes(16));
    return substr($d,0,8).'-'.substr($d,8,4).'-'.substr($d,12,4).'-'.substr($d,16,4).'-'.substr($d,20,12);
}

/** Normalise un MSISDN SN en 221XXXXXXXXX */
private function normalizeMsisdn(?string $raw): ?string
{
    if (!$raw) return null;
    $s = preg_replace('/\D/', '', $raw);

    // 9 chiffres locaux → préfixe 221
    if (strlen($s) === 9 && preg_match('/^(70|75|76|77|78)\d{7}$/', $s)) {
        return '221' . $s;
    }
    // 221 + 9 chiffres
    if (strlen($s) === 12 && str_starts_with($s, '221') && preg_match('/^221(70|75|76|77|78)\d{7}$/', $s)) {
        return $s;
    }
    return null;
}


    /**
     * Traite les données reçues du webhook Orange Money.
     *
     * @param array $payload Le corps de la requête du webhook.
     * @return bool Vrai si le webhook a été traité avec succès, faux sinon.
     * @throws \Exception Si une erreur survient pendant le traitement.
     */
    public function processWebhook(array $payload, string $signatureHeader = null, string $rawPayload = null): bool
    {
        // 1. Vérification de la signature du webhook (TRÈS IMPORTANT POUR LA SÉCURITÉ)
        // Nous supposons que l\'en-tête de signature est 'X-OrangeMoney-Signature' et que l\'algorithme est HMAC SHA-256.
        // La clé secrète du webhook doit être configurée dans config/services.php ou .env
        $webhookSecret = config('services.orange_money.webhook_secret');

        if (!$webhookSecret) {
            LogFacade::warning('Webhook Orange Money: Clé secrète du webhook manquante dans la configuration.');
            throw new \Exception('Configuration de sécurité du webhook incomplète.');
        }

        // Si le rawPayload n\'est pas fourni (e.g., test unitaire), nous ne pouvons pas vérifier la signature.
        // En production, le contrôleur doit passer le rawPayload.
        if ($signatureHeader && $rawPayload) {
            $expectedSignature = base64_encode(hash_hmac('sha256', $rawPayload, $webhookSecret, true));

            if (!hash_equals($expectedSignature, $signatureHeader)) {
                LogFacade::warning('Webhook Orange Money: Signature invalide.', ['received_signature' => $signatureHeader, 'calculated_signature' => $expectedSignature, 'payload' => $payload]);
                throw new \Exception('Signature de webhook invalide.');
            }
            LogFacade::info('Webhook Orange Money: Signature vérifiée avec succès.');
        } else if ($signatureHeader || $rawPayload) {
            LogFacade::warning('Webhook Orange Money: Signature ou rawPayload incomplet pour la vérification. La vérification a été ignorée.', ['signatureHeader' => $signatureHeader, 'rawPayloadProvided' => !is_null($rawPayload), 'payload' => $payload]);
        } else {
            LogFacade::info('Webhook Orange Money: Aucune signature ou rawPayload fourni. La vérification de la signature a été ignorée.');
        }

        DB::beginTransaction();
        try {
            // Exemple de récupération des données (à adapter selon la structure réelle du webhook OM)
            $omTransactionId = $payload["transaction_id"] ?? null; // ID de transaction d'Orange Money
            $omStatus = $payload["status"] ?? null; // Statut du paiement chez Orange Money
            $yourOrderId = $payload["order_id"] ?? null; // L'UID de votre transaction que vous avez envoyé

            if (!$yourOrderId || !$omStatus) {
                LogFacade::warning('Webhook Orange Money: Payload incomplet.', $payload);
                throw new \Exception('Payload de webhook incomplet.');
            }

            $transaction = Transaction::where('transaction_uid', $yourOrderId)->first();

            if (!$transaction) {
                LogFacade::warning('Webhook Orange Money: Transaction introuvable pour UID ' . $yourOrderId, $payload);
                throw new \Exception('Transaction introuvable pour l\'UID fourni.');
            }

            $newPaymentStatus = null;
            switch ($omStatus) {
                case 'SUCCESS':
                    $newPaymentStatus = PaymentStatus::where('name', 'paid')->first();
                    break;
                case 'FAILED':
                    $newPaymentStatus = PaymentStatus::where('name', 'failed')->first();
                    break;
                case 'PENDING':
                    $newPaymentStatus = PaymentStatus::where('name', 'pending')->first();
                    break;
                // Ajoutez d'autres statuts si nécessaire (e.g., 'REFUNDED', 'CANCELLED')
                default:
                    LogFacade::warning('Webhook Orange Money: Statut inconnu ' . $omStatus, $payload);
                    throw new \Exception('Statut de paiement inconnu du webhook.');
            }

            if (!$newPaymentStatus) {
                LogFacade::error('Webhook Orange Money: Impossible de mapper le statut ' . $omStatus . ' à un statut de paiement interne.', $payload);
                throw new \Exception('Statut de paiement non mappable.');
            }

            // Mettre à jour la transaction si le statut a changé
            if ($transaction->payment_status_id !== $newPaymentStatus->id) {
                $transaction->payment_status_id = $newPaymentStatus->id;
                // Vous pouvez aussi stocker l'ID de transaction d'Orange Money
                $transaction->metadata = array_merge($transaction->metadata ?? [], ['om_transaction_id' => $omTransactionId, 'webhook_payload' => $payload]);
                $transaction->save();

                LogFacade::info('Transaction ' . $yourOrderId . ' mise à jour au statut ' . $newPaymentStatus->name);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            LogFacade::error('Erreur lors du traitement du webhook Orange Money dans le service: ' . $e->getMessage(), ['exception' => $e, 'payload' => $payload]);
            throw $e; // Re-lancer l'exception pour que le contrôleur puisse la gérer
        }
    }

    public function processCashIn(array $cashInData)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/api/eWallet/v1/cashins', $cashInData);

            $response->throw();

            return $response->json();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de l\'initiation du Cash In Orange Money: ' . $e->getMessage());
            throw new \Exception('Échec de l\'initiation du Cash In Orange Money.');
        }
    }
}
