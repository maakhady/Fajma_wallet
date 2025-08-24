<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\OrangeMoneyService;
use App\Models\Transaction;
use App\Models\Card;
use App\Models\PaymentStatus;

class WebhookController extends Controller
{
    protected $orangeMoneyService;

    public function __construct(OrangeMoneyService $orangeMoneyService)
    {
        $this->orangeMoneyService = $orangeMoneyService;
    }

    /**
     * Webhook Orange Money
     */
    public function handleOrangeMoney(Request $request)
    {
        Log::info("📥 Webhook Orange Money reçu", $request->all());

        try {
            $rawPayload       = $request->getContent();
            $signatureHeader  = $request->header("X-OrangeMoney-Signature");

            // Traitement via le service OrangeMoneyService
            $this->orangeMoneyService->processWebhook($request->all(), $signatureHeader, $rawPayload);

            return response()->json([
                "status"  => "success",
                "message" => "Webhook Orange Money traité avec succès"
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error("❌ Erreur Webhook Orange Money : " . $e->getMessage(), ["exception" => $e]);
            return response()->json([
                "status"  => "error",
                "message" => "Échec traitement Webhook Orange Money"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Webhook Wave
     */
    public function handleWave(Request $request)
    {
        Log::info("📥 Webhook Wave reçu", $request->all());

        try {
            // 1) Vérif secret partagé
            $secret              = config('services.wave.webhook_secret');
            $authorizationHeader = $request->header('Authorization');

            if ($authorizationHeader !== 'Bearer ' . $secret) {
                Log::warning('❌ Webhook Wave refusé : clé secrète invalide', [
                    'provided' => $authorizationHeader
                ]);
                return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $payload   = $request->all();
            $eventType = $payload['type'] ?? null;
            $checkout  = $payload['data']['object'] ?? null;

            // 2) Paiement réussi
            if ($eventType === 'checkout.payment_succeeded' && $checkout) {
                $clientReference = $checkout['client_reference'] ?? null;
                $amountPaid      = $checkout['amount'] ?? null;

                if (!$clientReference || !$amountPaid) {
                    Log::error('❌ Webhook Wave incomplet', ['checkout' => $checkout]);
                    return response()->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
                }

                // 3) Retrouver la transaction
                $transaction = Transaction::where('transaction_uid', $clientReference)->first();
                if (!$transaction) {
                    Log::error("❌ Transaction introuvable pour UID: {$clientReference}");
                    return response()->json(['message' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
                }

                // 4) Statut payé et MAJ solde carte
                $paidStatus = PaymentStatus::where('name', 'paid')->first();
                if (!$paidStatus) {
                    Log::error("❌ Statut 'paid' introuvable");
                    return response()->json(['message' => 'Payment status not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                $transaction->payment_status_id = $paidStatus->id;
                $transaction->current_balance   = $transaction->previous_balance + $amountPaid;

                $card = Card::find($transaction->card_id);
                if ($card) {
                    $card->balance += $amountPaid;
                    $card->save();
                }

                $meta = (array)($transaction->metadata ?? []);
                $meta['wave_webhook'] = $checkout;
                $transaction->metadata = $meta;
                $transaction->save();

                Log::info("✅ Paiement Wave confirmé pour transaction UID {$clientReference}");

                return response()->json(['status' => 'success'], Response::HTTP_OK);
            }

            // 5) Événements non gérés
            Log::warning("⚠️ Événement Wave non géré : {$eventType}");
            return response()->json([
                'status' => 'ignored',
                'event'  => $eventType
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error("❌ Erreur Webhook Wave : " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Échec traitement Webhook Wave'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
