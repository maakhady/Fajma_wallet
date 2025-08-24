<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transaction\CreateDepositRequest;
use App\Http\Requests\Transaction\CreatePaymentRequest;
use App\Http\Requests\Transaction\ListTransactionRequest;
use App\Http\Requests\Transaction\UpdateStatusRequest;
use App\Models\Log;
use App\Services\TransactionService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    protected $transactionService;

   /**
     * Constructeur avec injection du service et middleware d'authentification
     *
     * @param TransactionService $transactionService
     */
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;

        // Vérifier d'abord si la méthode middleware existe
        if (method_exists($this, 'middleware')) {
            // Appliquer le middleware d'authentification à toutes les routes
            $this->middleware('auth:api');
        }
    }

     /**
     * Vérifie si l'utilisateur est un administrateur
     *
     * @return bool
     */
    protected function isAdmin()
    {
        return Auth::user()->role === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est un administrateur et renvoie une erreur si ce n'est pas le cas
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function checkAdmin()
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'error' => 'Accès non autorisé. Rôle administrateur requis.'
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Liste des transactions de l'utilisateur connecté
     *
     * @param ListTransactionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ListTransactionRequest $request)
    {
        try {
            $user = Auth::user();
            $filters = $request->validated();

            $transactions = $this->transactionService->getUserTransactions($user->id, $filters);

            return response()->json([
                'transactions' => $transactions,
                'pagination' => [
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'last_page' => $transactions->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération transactions: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des transactions'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Liste de toutes les transactions (Admin uniquement)
     *
     * @param ListTransactionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexAdmin(ListTransactionRequest $request)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $filters = $request->validated();

            $transactions = $this->transactionService->getAllTransactions($filters);

            return response()->json([
                'transactions' => $transactions,
                'pagination' => [
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'last_page' => $transactions->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération transactions admin: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des transactions'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les détails d'une transaction spécifique
     *
     * @param string $transactionUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($transactionUid)
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionService->getUserTransactionByUid($user->id, $transactionUid);

            if (!$transaction) {
                return response()->json([
                    'error' => 'Transaction introuvable'
                ], Response::HTTP_NOT_FOUND);
            }

            // Journaliser la consultation
            Log::create([
                'user_id' => $user->id,
                'action' => 'view_transaction',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => 'Consultation des détails de la transaction ' . $transaction->transaction_uid
            ]);

            return response()->json([
                'transaction' => $transaction
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération transaction: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération de la transaction'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les détails d'une transaction spécifique (Admin uniquement)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAdmin($id)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $transaction = $this->transactionService->getTransactionById($id);

            if (!$transaction) {
                return response()->json([
                    'error' => 'Transaction introuvable'
                ], Response::HTTP_NOT_FOUND);
            }

            $user = Auth::user();

            // Journaliser la consultation
            Log::create([
                'user_id' => $user->id,
                'action' => 'view_transaction_admin',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => 'Consultation admin des détails de la transaction ' . $transaction->transaction_uid
            ]);

            return response()->json([
                'transaction' => $transaction
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération transaction admin: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération de la transaction'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Créer une transaction de dépôt
     *
     * @param CreateDepositRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deposit(CreateDepositRequest $request)
    {
        try {
            $user = Auth::user();
            $data = $request->validated();

            // Assurer que c'est pour l'utilisateur connecté
            $data['user_id'] = $user->id;

            // Créer le dépôt via le service
            $result = $this->transactionService->processDeposit($data);

            if (!$result["success"]) {
                return response()->json([
                    "error" => $result["message"],
                    "details" => $result["errors"] ?? null
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Si le service a initié un Cash In asynchrone
            // (Vous pouvez ajouter un indicateur dans $result si nécessaire, ex: $result["cash_in_initiated"])
            return response()->json([
                "message" => "Dépôt initié avec succès. En attente de confirmation.",
                "transaction" => $result["transaction"]
            ], Response::HTTP_ACCEPTED); // Ou HTTP_OK
        } catch (\Exception $e) {
            LogFacade::error('Erreur création dépôt: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la création du dépôt: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Créer une transaction de paiement
     *
     * @param CreatePaymentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function payment(CreatePaymentRequest $request)
    {
        try {
            $user = Auth::user();
            $data = $request->validated();

            // Assurer que c'est pour l'utilisateur connecté
            $data['user_id'] = $user->id;

            // Créer le paiement via le service
            $result = $this->transactionService->processPayment($data);

            if (!$result["success"]) {
                return response()->json([
                    "error" => $result["message"],
                    "details" => $result["errors"] ?? null
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Si le service a retourné des données de QR Code, le paiement est en attente
            if (isset($result["qr_code_data"])) {
                return response()->json([
                    "message" => "QR Code généré avec succès. Veuillez scanner le QR Code pour finaliser le paiement.",
                    "transaction" => $result["transaction"],
                    "qr_code_data" => $result["qr_code_data"]
                ], Response::HTTP_ACCEPTED); // Ou HTTP_OK si vous préférez
            } else {
                // Cas où le paiement est immédiatement réussi (moins probable avec QR Code)
                return response()->json([
                    "message" => "Paiement effectué avec succès",
                    "transaction" => $result["transaction"]
                ], Response::HTTP_CREATED);
            }
        } catch (\Exception $e) {
            LogFacade::error('Erreur création paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la création du paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Annuler une transaction (utilisateur ou admin)
     *
     * @param string $transactionUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($transactionUid)
    {
        try {
            $user = Auth::user();

            // Annuler la transaction via le service
            $result = $this->transactionService->cancelTransaction($transactionUid, $user->id, $this->isAdmin());

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => 'Transaction annulée avec succès',
                'transaction' => $result['transaction']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur annulation transaction: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de l\'annulation de la transaction: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour le statut d'une transaction (Admin uniquement)
     *
     * @param UpdateStatusRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(UpdateStatusRequest $request, $id)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $data = $request->validated();

            // Mettre à jour le statut via le service
            $result = $this->transactionService->updateTransactionStatus($id, $data['payment_status_id'], $data['reason']);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => 'Statut de la transaction mis à jour avec succès',
                'transaction' => $result['transaction']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour statut transaction: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise à jour du statut de la transaction: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les statistiques des transactions (Admin uniquement)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $period = $request->input('period', 'month');
            $userId = $request->input('user_id');

            // Récupérer les statistiques via le service
            $stats = $this->transactionService->getStatistics($period, $userId);

            return response()->json([
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération statistiques: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les statistiques des transactions de l'utilisateur connecté
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myStatistics(Request $request)
    {
        try {
            $user = Auth::user();
            $period = $request->input('period', 'month');

            // Récupérer les statistiques de l'utilisateur via le service
            $stats = $this->transactionService->getUserStatistics($user->id, $period);

            return response()->json([
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération statistiques utilisateur: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération de vos statistiques: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Générer un reçu pour une transaction
     *
     * @param string $transactionUid
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function receipt($transactionUid)
    {
        try {
            $user = Auth::user();

            // Récupérer le reçu de la transaction via le service
            $receipt = $this->transactionService->generateReceipt($transactionUid, $user->id, $this->isAdmin());

            if (!$receipt['success']) {
                return response()->json([
                    'error' => $receipt['message']
                ], Response::HTTP_NOT_FOUND);
            }

            // Retourner le PDF
            return $receipt['pdf']->stream('recu-transaction-' . $transactionUid . '.pdf');
        } catch (\Exception $e) {
            LogFacade::error('Erreur génération reçu: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la génération du reçu: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
 * Exporter les transactions (Admin uniquement)
 *
 * @param Request $request
 * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
 */
public function export(Request $request)
{
    // Vérification des droits administrateur
    $adminCheck = $this->checkAdmin();
    if ($adminCheck) return $adminCheck;

    try {
        $format = $request->input('format', 'csv');
        $filters = $request->except(['format']);

        // Exporter les transactions via le service
        $export = $this->transactionService->exportTransactions($filters, $format);

        if (!isset($export['success']) || !$export['success']) {
            return response()->json([
                'error' => $export['message'] ?? 'Erreur lors de l\'export'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($format === 'csv') {
            return response($export['content'])
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="transactions-export-' . date('Y-m-d') . '.csv"');
        } else if ($format === 'excel' && isset($export['excel'])) {
            // Télécharger le fichier Excel
            return response()->download(
                $export['excel']['path'],
                $export['excel']['filename'],
                ['Content-Type' => $export['excel']['mime']]
            )->deleteFileAfterSend(true); // Supprimer le fichier temporaire après envoi
        } else if ($format === 'excel' && isset($export['content'])) {
            // Fallback au CSV si l'Excel a échoué
            return response($export['content'])
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="transactions-export-' . date('Y-m-d') . '.csv"');
        } else if ($format === 'pdf') {
            // Pour l'instant, renvoyer CSV aussi pour PDF
            return response($export['content'])
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="transactions-export-' . date('Y-m-d') . '.csv"');
        }

        return response()->json([
            'error' => 'Format d\'export non pris en charge'
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    } catch (\Exception $e) {
        LogFacade::error('Erreur export transactions: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de l\'export des transactions: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Récupérer le rapport quotidien des transactions (Admin uniquement)
     *
     * @param string|null $date
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailyReport($date = null)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            if (!$date) {
                $date = date('Y-m-d');
            }

            // Récupérer le rapport quotidien via le service
            $report = $this->transactionService->getDailyReport($date);

            return response()->json([
                'daily_report' => $report
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur rapport quotidien: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération du rapport quotidien: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer le rapport mensuel des transactions (Admin uniquement)
     *
     * @param string|null $month
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyReport($month = null)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            if (!$month) {
                $month = date('Y-m');
            }

            // Récupérer le rapport mensuel via le service
            $report = $this->transactionService->getMonthlyReport($month);

            return response()->json([
                'monthly_report' => $report
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur rapport mensuel: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération du rapport mensuel: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Exporter les transactions au format PDF (Admin uniquement)
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function exportPdf(Request $request)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $filters = $request->all();

            // Déléguer l'export au service
            $result = $this->transactionService->exportTransactionsPdf($filters);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Journaliser l'action
            Log::create([
                'user_id' => Auth::id(),
                'action' => 'export_transactions_pdf',
                'description' => 'Export PDF de ' . ($result['count'] ?? 0) . ' transactions'
            ]);

            // Retourner le PDF
            return $result['pdf']->download('transactions-' . now()->format('Y-m-d-His') . '.pdf');
        } catch (\Exception $e) {
            LogFacade::error('Erreur export PDF: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de l\'export PDF des transactions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Suppression logique d'une transaction
     *
     * @param string $transactionUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function softDelete($transactionUid)
    {
        try {
            $user = Auth::user();

            // Appeler le service pour effectuer la suppression logique
            $result = $this->transactionService->softDeleteTransaction($transactionUid, $user->id, $this->isAdmin());

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression logique transaction: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression de la transaction: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
 * Récupérer la liste des transactions supprimées logiquement
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function trashed(Request $request)
{
    try {
        // Récupérer les paramètres de filtrage
        $filters = $request->all();

        // Appeler le service pour récupérer les transactions supprimées
        $result = $this->transactionService->getTrashedTransactions(
            $filters,
            Auth::id(),
            false // Non-admin par défaut
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json($result['transactions']);
    } catch (\Exception $e) {
        LogFacade::error('Erreur trashed: ' . $e->getMessage());

        return response()->json([
            'error' => 'Une erreur est survenue lors de la récupération des transactions supprimées',
            'details' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * Récupérer la liste des transactions supprimées (version admin)
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function trashedAdmin(Request $request)
{
    // Vérification des droits administrateur
    $adminCheck = $this->checkAdmin();
    if ($adminCheck) return $adminCheck;

    try {
        // Récupérer les paramètres de filtrage
        $filters = $request->all();

        // Récupérer l'ID utilisateur spécifique si fourni
        $userId = $request->input('user_id');

        // Appeler le service pour récupérer les transactions supprimées
        $result = $this->transactionService->getTrashedTransactions(
            $filters,
            $userId,
            true // Version admin
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json($result['transactions']);
    } catch (\Exception $e) {
        LogFacade::error('Erreur trashedAdmin: ' . $e->getMessage());

        return response()->json([
            'error' => 'Une erreur est survenue lors de la récupération des transactions supprimées',
            'details' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Suppression logique des transactions pour une période donnée
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function softDeleteByPeriod(Request $request)
    {
        try {
            $user = Auth::user();

            // Valider les données de la requête
            $validatedData = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'status_ids' => 'nullable|array',
                'status_ids.*' => 'exists:payment_statuses,id'
            ]);

            // Appeler le service pour effectuer la suppression logique
            $result = $this->transactionService->softDeleteTransactionsByPeriod($validatedData, $user->id, $this->isAdmin());

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => $result['message'],
                'count' => $result['count']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression logique transactions par période: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression des transactions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restaurer une transaction supprimée logiquement
     *
     * @param string $transactionUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($transactionUid)
    {
        try {
            $user = Auth::user();

            // Appeler le service pour effectuer la restauration
            $result = $this->transactionService->restoreTransaction($transactionUid, $user->id, $this->isAdmin());

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => $result['message'],
                'transaction' => $result['transaction']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur restauration transaction: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la restauration de la transaction: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restaurer les transactions supprimées logiquement pour une période donnée
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreByPeriod(Request $request)
    {
        try {
            $user = Auth::user();

            // Valider les données de la requête
            $validatedData = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'status_ids' => 'nullable|array',
                'status_ids.*' => 'exists:payment_statuses,id'
            ]);

            // Appeler le service pour effectuer la restauration
            $result = $this->transactionService->restoreTransactionsByPeriod($validatedData, $user->id, $this->isAdmin());

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => $result['message'],
                'count' => $result['count']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur restauration transactions par période: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la restauration des transactions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Suppression définitive d'une transaction (Admin uniquement)
     *
     * @param string $transactionUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete($transactionUid)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $user = Auth::user();

            // Appeler le service pour effectuer la suppression définitive
            $result = $this->transactionService->forceDeleteTransaction($transactionUid, $user->id, true);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression définitive transaction: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression définitive de la transaction: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Suppression définitive des transactions pour une période donnée (Admin uniquement)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDeleteByPeriod(Request $request)
    {
        // Vérification des droits administrateur
        $adminCheck = $this->checkAdmin();
        if ($adminCheck) return $adminCheck;

        try {
            $user = Auth::user();

            // Valider les données de la requête
            $validatedData = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'status_ids' => 'nullable|array',
                'status_ids.*' => 'exists:payment_statuses,id'
            ]);

            // Appeler le service pour effectuer la suppression définitive
            $result = $this->transactionService->forceDeleteTransactionsByPeriod($validatedData, $user->id, true);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => $result['message'],
                'count' => $result['count']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression définitive transactions par période: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression définitive des transactions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
