<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // ✅ AJOUTÉ
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
        Log::info("🔥 Webhook Wave reçu", $request->all());

        try {
            // ✅ Vérification signature HMAC (recommandé)
            $signature = $request->header('Wave-Signature');
            $rawPayload = $request->getContent();
            $secret = config('services.wave.webhook_secret');

            if ($signature && $secret) {
                // Vérification HMAC SHA256
                $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);
                
                if (!hash_equals($expectedSignature, $signature)) {
                    Log::warning('❌ Signature Wave invalide', [
                        'expected' => $expectedSignature,
                        'received' => $signature
                    ]);
                    return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
                }
            } elseif (!$signature && $secret) {
                // Fallback: vérification Bearer token si pas de signature
                $authorizationHeader = $request->header('Authorization');
                if ($authorizationHeader !== 'Bearer ' . $secret) {
                    Log::warning('❌ Webhook Wave refusé : clé secrète invalide');
                    return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
                }
            }

            $payload = $request->all();
            $eventType = $payload['type'] ?? null;
            $checkout = $payload['data']['object'] ?? null;

            Log::info("📋 Event Wave: {$eventType}");

            // ✅ Paiement réussi (nom d'événement corrigé)
            if ($eventType === 'checkout.session.completed' && $checkout) {
                return $this->handleWavePaymentSuccess($checkout);
            }

            // ✅ Paiement échoué (nom d'événement corrigé)
            if ($eventType === 'checkout.session.payment_failed' && $checkout) {
                return $this->handleWavePaymentFailed($checkout);
            }

            // Événements non gérés
            Log::warning("⚠️ Événement Wave non géré : {$eventType}");
            return response()->json([
                'status' => 'ignored',
                'event' => $eventType
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error("❌ Erreur Webhook Wave : " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Échec traitement Webhook Wave'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Traite le succès de paiement Wave
     */
    private function handleWavePaymentSuccess(array $checkout)
    {
        $clientReference = $checkout['client_reference'] ?? null;
        $amountPaid = $checkout['amount'] ?? null;

        if (!$clientReference || !$amountPaid) {
            Log::error('❌ Webhook Wave incomplet', ['checkout' => $checkout]);
            return response()->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        // Retrouver la transaction
        $transaction = Transaction::where('transaction_uid', $clientReference)->first();
        
        if (!$transaction) {
            Log::error("❌ Transaction introuvable pour UID: {$clientReference}");
            return response()->json(['message' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        // ✅ Vérification idempotence (éviter double traitement)
        if ($transaction->payment_status?->name === 'paid') {
            Log::info("ℹ️ Transaction déjà marquée comme payée", [
                'transaction_uid' => $clientReference
            ]);
            return response()->json(['status' => 'already_processed'], Response::HTTP_OK);
        }

        // Statut payé
        $paidStatus = PaymentStatus::where('name', 'paid')->first();
        
        if (!$paidStatus) {
            Log::error("❌ Statut 'paid' introuvable");
            return response()->json([
                'message' => 'Payment status not found'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        DB::beginTransaction();
        try {
            $transaction->payment_status_id = $paidStatus->id;
            $transaction->current_balance = $transaction->previous_balance + $amountPaid;

            $card = Card::find($transaction->card_id);
            if ($card) {
                $card->balance += $amountPaid;
                $card->save();
            }

            $meta = (array)($transaction->metadata ?? []);
            $meta['wave_webhook'] = $checkout;
            $transaction->metadata = $meta;
            $transaction->save();

            // Log de l'action
            \App\Models\Log::create([
                'user_id' => $transaction->user_id,
                'action' => 'wave_payment_success',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => "Paiement Wave confirmé: {$amountPaid} FCFA"
            ]);

            DB::commit();

            Log::info("✅ Paiement Wave confirmé pour transaction UID {$clientReference}");

            return response()->json(['status' => 'success'], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Erreur traitement succès Wave: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Traite l'échec de paiement Wave
     */
    private function handleWavePaymentFailed(array $checkout)
    {
        $clientReference = $checkout['client_reference'] ?? null;

        if (!$clientReference) {
            Log::error('❌ Webhook Wave échec incomplet', ['checkout' => $checkout]);
            return response()->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $transaction = Transaction::where('transaction_uid', $clientReference)->first();
        
        if (!$transaction) {
            Log::error("❌ Transaction introuvable pour UID: {$clientReference}");
            return response()->json(['message' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        $failedStatus = PaymentStatus::where('name', 'failed')->first();
        
        if ($failedStatus) {
            $transaction->payment_status_id = $failedStatus->id;
            
            $meta = (array)($transaction->metadata ?? []);
            $meta['wave_failure'] = $checkout;
            $transaction->metadata = $meta;
            $transaction->save();
        }

        Log::warning("⚠️ Paiement Wave échoué pour transaction UID {$clientReference}");

        return response()->json(['status' => 'failed'], Response::HTTP_OK);
    }
}