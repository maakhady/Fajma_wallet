<?php

namespace App\Http\Controllers;

use App\Models\PaymentStatus;
use App\Services\PaymentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\PaymentStatus\StorePaymentStatusRequest;
use App\Http\Requests\PaymentStatus\UpdatePaymentStatusRequest;

class PaymentStatusController extends Controller
{
    protected $paymentStatusService;

    /**
     * Constructeur avec injection du service et middleware d'authentification
     *
     * @param PaymentStatusService $paymentStatusService
     */
    public function __construct(PaymentStatusService $paymentStatusService)
    {
        $this->paymentStatusService = $paymentStatusService;

        if (method_exists($this, 'middleware')) {
            // Routes accessibles à tous
            $this->middleware('auth:api')->except(['index', 'show']);
            
            // Routes réservées aux administrateurs
            $this->middleware('role:admin')->only([
                'store', 'update', 'destroy', 'initialize', 'getTransactionsCount'
            ]);
        }
    }

    /**
     * Afficher la liste des statuts de paiement
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $paymentStatuses = $this->paymentStatusService->getAllPaymentStatuses();

            return response()->json([
                'payment_statuses' => $paymentStatuses
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération statuts de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des statuts de paiement'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les détails d'un statut de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $paymentStatus = $this->paymentStatusService->getPaymentStatusById($id);

            return response()->json([
                'payment_status' => $paymentStatus
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération statut de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Statut de paiement introuvable'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Créer un nouveau statut de paiement
     *
     * @param StorePaymentStatusRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StorePaymentStatusRequest $request)
    {
        try {
            // Les données sont déjà validées par la classe de requête
            $validatedData = $request->validated();

            // Créer le statut de paiement
            $paymentStatus = $this->paymentStatusService->createPaymentStatus($validatedData);

            return response()->json([
                'message' => 'Statut de paiement créé avec succès',
                'payment_status' => $paymentStatus
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            LogFacade::error('Erreur création statut de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la création du statut de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour un statut de paiement existant
     *
     * @param UpdatePaymentStatusRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePaymentStatusRequest $request, $id)
    {
        try {
            // Les données sont déjà validées par la classe de requête
            $validatedData = $request->validated();

            // Mettre à jour le statut de paiement
            $paymentStatus = $this->paymentStatusService->updatePaymentStatus($id, $validatedData);

            return response()->json([
                'message' => 'Statut de paiement mis à jour avec succès',
                'payment_status' => $paymentStatus
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour statut de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise à jour du statut de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer un statut de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $this->paymentStatusService->deletePaymentStatus($id);

            return response()->json([
                'message' => 'Statut de paiement supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression statut de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression du statut de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Initialiser les statuts de paiement par défaut
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function initialize()
    {
        try {
            $createdStatuses = $this->paymentStatusService->initializeDefaultStatuses();

            return response()->json([
                'message' => count($createdStatuses) . ' statuts de paiement initialisés avec succès',
                'created_statuses' => $createdStatuses
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur initialisation statuts de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de l\'initialisation des statuts de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtenir le nombre de transactions par statut
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionsCount()
    {
        try {
            $statusCounts = $this->paymentStatusService->getTransactionsCountByStatus();

            return response()->json([
                'status_counts' => $statusCounts
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur comptage des transactions par statut: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors du comptage des transactions par statut: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}