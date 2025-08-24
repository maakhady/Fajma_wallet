<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMean\StorePaymentMeanRequest;
use App\Http\Requests\PaymentMean\UpdatePaymentMeanRequest;
use App\Http\Requests\PaymentMean\ValidateIdentifierRequest;
use App\Http\Requests\PaymentMean\SetDefaultPaymentMeanRequest;
use App\Http\Requests\PaymentMean\GetUserPaymentMeansRequest;
use App\Http\Requests\PaymentMean\DecryptAccountIdentifierRequest;
use App\Models\PaymentMean;
use App\Services\PaymentMeanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\Response;

class PaymentMeanController extends Controller
{
    protected $paymentMeanService;

    /**
     * Constructeur avec injection du service et middleware d'authentification
     *
     * @param PaymentMeanService $paymentMeanService
     */
    public function __construct(PaymentMeanService $paymentMeanService)
    {
        $this->paymentMeanService = $paymentMeanService;
        if (method_exists($this, 'middleware')) {
            // Toutes les routes nécessitent une authentification
            $this->middleware('auth:api');
            // Routes spécifiques aux administrateurs
            $this->middleware('role:admin')->only([
                'index', 'destroy', 'trashed', 'restore', 'forceDelete',
                'getTransactionsCount'
            ]);
        }
    }

    /**
     * Afficher la liste de tous les moyens de paiement (admin uniquement)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $paymentMeans = $this->paymentMeanService->getAllPaymentMeans();
            return response()->json([
                'payment_means' => $paymentMeans
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération moyens de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des moyens de paiement'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les moyens de paiement de l'utilisateur connecté
     *
     * @param GetUserPaymentMeansRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPaymentMeans(GetUserPaymentMeansRequest $request)
    {
        try {
            $user = Auth::user();
            $data = $request->validated();
            $onlyActive = isset($data['active_only']) && $data['active_only'] === 'true';
            $paymentMeans = $this->paymentMeanService->getUserPaymentMeans($user->id, $onlyActive);
            return response()->json([
                'payment_means' => $paymentMeans
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération moyens de paiement utilisateur: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération de vos moyens de paiement'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher le moyen de paiement par défaut de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserDefaultPaymentMean()
    {
        try {
            $user = Auth::user();
            $defaultPaymentMean = $this->paymentMeanService->getUserDefaultPaymentMean($user->id);
            if (!$defaultPaymentMean) {
                return response()->json([
                    'message' => 'Aucun moyen de paiement par défaut défini'
                ], Response::HTTP_NOT_FOUND);
            }
            return response()->json([
                'default_payment_mean' => $defaultPaymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération moyen de paiement par défaut: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération de votre moyen de paiement par défaut'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les détails d'un moyen de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $paymentMean = $this->paymentMeanService->getPaymentMeanById($id);
            $user = Auth::user();
            if ($user->role !== 'admin' && $user->id !== $paymentMean->user_id) {
                return response()->json([
                    'error' => 'Vous n\'êtes pas autorisé à consulter ce moyen de paiement'
                ], Response::HTTP_FORBIDDEN);
            }
            return response()->json([
                'payment_mean' => $paymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Moyen de paiement introuvable'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Créer un nouveau moyen de paiement
     *
     * @param StorePaymentMeanRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StorePaymentMeanRequest $request)
    {
        try {
            $data = $request->validated();
            $user = Auth::user();
            if ($user->role !== 'admin' && (!isset($data['user_id']) || $data['user_id'] != $user->id)) {
                $data['user_id'] = $user->id;
            }

            if (!$this->paymentMeanService->validateAccountIdentifier($data['account_identifier'], $data['payment_type_id'])) {
                return response()->json([
                    'error' => 'L\'identifiant de compte fourni n\'est pas valide pour ce type de paiement'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $paymentMean = $this->paymentMeanService->createPaymentMean($data);
            return response()->json([
                'message' => 'Moyen de paiement créé avec succès',
                'payment_mean' => $paymentMean
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            LogFacade::error('Erreur création moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la création du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour un moyen de paiement existant
     *
     * @param UpdatePaymentMeanRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePaymentMeanRequest $request, $id)
    {
        try {
            $paymentMean = $this->paymentMeanService->getPaymentMeanById($id);
            $user = Auth::user();
            if ($user->role !== 'admin' && $user->id !== $paymentMean->user_id) {
                return response()->json([
                    'error' => 'Vous n\'êtes pas autorisé à modifier ce moyen de paiement'
                ], Response::HTTP_FORBIDDEN);
            }

            $data = $request->validated();
            if (isset($data['account_identifier']) && isset($data['payment_type_id'])) {
                if (!$this->paymentMeanService->validateAccountIdentifier($data['account_identifier'], $data['payment_type_id'])) {
                    return response()->json([
                        'error' => 'L\'identifiant de compte fourni n\'est pas valide pour ce type de paiement'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            } elseif (isset($data['account_identifier'])) {
                if (!$this->paymentMeanService->validateAccountIdentifier($data['account_identifier'], $paymentMean->payment_type_id)) {
                    return response()->json([
                        'error' => 'L\'identifiant de compte fourni n\'est pas valide pour ce type de paiement'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $updatedPaymentMean = $this->paymentMeanService->updatePaymentMean($id, $data);
            return response()->json([
                'message' => 'Moyen de paiement mis à jour avec succès',
                'payment_mean' => $updatedPaymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer un moyen de paiement (soft delete)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $paymentMean = $this->paymentMeanService->getPaymentMeanById($id);
            $user = Auth::user();
            if ($user->role !== 'admin' && $user->id !== $paymentMean->user_id) {
                return response()->json([
                    'error' => 'Vous n\'êtes pas autorisé à supprimer ce moyen de paiement'
                ], Response::HTTP_FORBIDDEN);
            }

            $this->paymentMeanService->deletePaymentMean($id);
            return response()->json([
                'message' => 'Moyen de paiement supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la suppression du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les moyens de paiement supprimés
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function trashed()
    {
        try {
            $trashedPaymentMeans = $this->paymentMeanService->getTrashedPaymentMeans();
            return response()->json([
                'trashed_payment_means' => $trashedPaymentMeans
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération moyens de paiement supprimés: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des moyens de paiement supprimés'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restaurer un moyen de paiement supprimé
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            $restoredPaymentMean = $this->paymentMeanService->restorePaymentMean($id);
            return response()->json([
                'message' => 'Moyen de paiement restauré avec succès',
                'payment_mean' => $restoredPaymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur restauration moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la restauration du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer définitivement un moyen de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete($id)
    {
        try {
            $this->paymentMeanService->forceDeletePaymentMean($id);
            return response()->json([
                'message' => 'Moyen de paiement supprimé définitivement avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression définitive moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la suppression définitive du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activer un moyen de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate($id)
    {
        try {
            $paymentMean = $this->paymentMeanService->getPaymentMeanById($id);
            $user = Auth::user();
            if ($user->role !== 'admin' && $user->id !== $paymentMean->user_id) {
                return response()->json([
                    'error' => 'Vous n\'êtes pas autorisé à activer ce moyen de paiement'
                ], Response::HTTP_FORBIDDEN);
            }

            $activatedPaymentMean = $this->paymentMeanService->activatePaymentMean($id);
            return response()->json([
                'message' => 'Moyen de paiement activé avec succès',
                'payment_mean' => $activatedPaymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur activation moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de l\'activation du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Désactiver un moyen de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate($id)
    {
        try {
            $paymentMean = $this->paymentMeanService->getPaymentMeanById($id);
            $user = Auth::user();
            if ($user->role !== 'admin' && $user->id !== $paymentMean->user_id) {
                return response()->json([
                    'error' => 'Vous n\'êtes pas autorisé à désactiver ce moyen de paiement'
                ], Response::HTTP_FORBIDDEN);
            }

            $deactivatedPaymentMean = $this->paymentMeanService->deactivatePaymentMean($id);
            return response()->json([
                'message' => 'Moyen de paiement désactivé avec succès',
                'payment_mean' => $deactivatedPaymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur désactivation moyen de paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la désactivation du moyen de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Définir un moyen de paiement comme moyen par défaut
     *
     * @param SetDefaultPaymentMeanRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function setAsDefault(SetDefaultPaymentMeanRequest $request, $id)
    {
        try {
            $paymentMean = $this->paymentMeanService->getPaymentMeanById($id);
            $user = Auth::user();
            if ($user->role !== 'admin' && $user->id !== $paymentMean->user_id) {
                return response()->json([
                    'error' => 'Vous n\'êtes pas autorisé à modifier ce moyen de paiement'
                ], Response::HTTP_FORBIDDEN);
            }

            $defaultPaymentMean = $this->paymentMeanService->setAsDefault($id);
            return response()->json([
                'message' => 'Moyen de paiement défini comme par défaut avec succès',
                'payment_mean' => $defaultPaymentMean
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur définition moyen de paiement par défaut: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la définition du moyen de paiement par défaut: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Valider un identifiant de compte
     *
     * @param ValidateIdentifierRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateIdentifier(ValidateIdentifierRequest $request)
    {
        try {
            $data = $request->validated();
            $isValid = $this->paymentMeanService->validateAccountIdentifier(
                $data['account_identifier'],
                $data['payment_type_id']
            );
            return response()->json([
                'is_valid' => $isValid
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur validation identifiant: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la validation de l\'identifiant: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtenir le nombre de transactions par moyen de paiement
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionsCount()
    {
        try {
            $transactionsCount = $this->paymentMeanService->getTransactionsCountByPaymentMean();
            return response()->json([
                'transactions_count' => $transactionsCount
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur comptage transactions: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du comptage des transactions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Décrypter l'identifiant de compte (seulement pour l'administrateur ou le propriétaire)
     *
     * @param DecryptAccountIdentifierRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function decryptAccountIdentifier(DecryptAccountIdentifierRequest $request, $id)
    {
        try {
            $decryptedIdentifier = $this->paymentMeanService->decryptAccountIdentifier($id);
            return response()->json([
                'account_identifier' => $decryptedIdentifier
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur décryptage identifiant: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du décryptage de l\'identifiant: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
