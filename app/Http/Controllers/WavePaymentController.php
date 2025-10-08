<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Services\WaveService;

class WavePaymentController extends Controller
{
    private WaveService $waveService;

    public function __construct(WaveService $waveService)
    {
        $this->waveService = $waveService;
    }

    /**
     * Redirection après paiement réussi
     * 
     * GET /payment/wave/success?session_id=xxx&client_reference=xxx
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        $clientReference = $request->query('client_reference');

        Log::info('✅ Redirection succès Wave', [
            'session_id' => $sessionId,
            'client_reference' => $clientReference,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Optionnel : Vérifier le statut de la session auprès de Wave
        try {
            if ($sessionId) {
                $sessionData = $this->waveService->getCheckoutSession($sessionId);
                
                Log::info('📊 Statut session Wave récupéré', [
                    'session_id' => $sessionId,
                    'status' => $sessionData['status'] ?? 'unknown'
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('⚠️ Impossible de récupérer le statut de la session Wave', [
                'error' => $e->getMessage()
            ]);
        }

        // Récupérer la transaction (optionnel, pour afficher des infos)
        $transaction = null;
        if ($clientReference) {
            $transaction = Transaction::where('transaction_uid', $clientReference)->first();
        }

        // Rediriger vers le frontend avec les paramètres
        $frontendUrl = config('app.frontend_url');
        
        return redirect($frontendUrl . '/payment/success?' . http_build_query([
            'provider' => 'wave',
            'reference' => $clientReference,
            'session_id' => $sessionId,
            'status' => 'success',
            'amount' => $transaction?->amount ?? null,
        ]));
    }

    /**
     * Redirection après paiement échoué
     * 
     * GET /payment/wave/error?session_id=xxx&client_reference=xxx
     */
    public function error(Request $request)
    {
        $sessionId = $request->query('session_id');
        $clientReference = $request->query('client_reference');

        Log::warning('⚠️ Redirection erreur Wave', [
            'session_id' => $sessionId,
            'client_reference' => $clientReference,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Optionnel : Récupérer les détails de l'erreur depuis Wave
        $errorReason = null;
        try {
            if ($sessionId) {
                $sessionData = $this->waveService->getCheckoutSession($sessionId);
                $errorReason = $sessionData['failure_reason'] ?? null;
                
                Log::info('📊 Détails échec Wave récupérés', [
                    'session_id' => $sessionId,
                    'reason' => $errorReason
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('⚠️ Impossible de récupérer les détails de l\'échec Wave', [
                'error' => $e->getMessage()
            ]);
        }

        // Récupérer la transaction
        $transaction = null;
        if ($clientReference) {
            $transaction = Transaction::where('transaction_uid', $clientReference)->first();
        }

        // Rediriger vers le frontend avec les paramètres d'erreur
        $frontendUrl = config('app.frontend_url');
        
        return redirect($frontendUrl . '/payment/error?' . http_build_query([
            'provider' => 'wave',
            'reference' => $clientReference,
            'session_id' => $sessionId,
            'status' => 'failed',
            'reason' => $errorReason ?? 'Payment failed',
            'amount' => $transaction?->amount ?? null,
        ]));
    }

    /**
     * Vérifier manuellement le statut d'une transaction
     * Utile si le webhook n'a pas été reçu
     * 
     * GET /api/payment/wave/check/{sessionId}
     */
    public function checkStatus(string $sessionId)
    {
        try {
            Log::info('🔍 Vérification manuelle statut Wave', [
                'session_id' => $sessionId
            ]);

            $sessionData = $this->waveService->getCheckoutSession($sessionId);

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'status' => $sessionData['status'] ?? 'unknown',
                'amount' => $sessionData['amount'] ?? null,
                'client_reference' => $sessionData['client_reference'] ?? null,
                'data' => $sessionData
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur vérification statut Wave', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to check payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}