<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use App\Services\PaymentTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\PaymentType\StorePaymentTypeRequest;
use App\Http\Requests\PaymentType\UpdatePaymentTypeRequest;
use App\Http\Requests\PaymentType\UpdateConfigRequest;
use App\Http\Requests\PaymentType\ConfigureWaveRequest;
use App\Http\Requests\PaymentType\ConfigureOrangeMoneyRequest;

class PaymentTypeController extends Controller
{
    protected $paymentTypeService;

    /**
     * Constructeur avec injection du service et middleware d'authentification
     *
     * @param PaymentTypeService $paymentTypeService
     */
    public function __construct(PaymentTypeService $paymentTypeService)
    {
        $this->paymentTypeService = $paymentTypeService;

        // Appliquer les middlewares d'authentification et d'autorisation de manière précise
        if (method_exists($this, 'middleware')) {
            // Routes publiques (index et show)

            // Routes protégées par authentification et rôle admin
            $this->middleware('auth:api')->except(['index', 'show']);
            $this->middleware('role:admin')->except(['index', 'show']);

            // Routes spécifiques avec autorisations supplémentaires pourraient être ajoutées ici
            // Exemple: $this->middleware('can:update-config')->only(['updateConfig']);
        }
    }

    /**
     * Afficher la liste des types de paiement
     * Accessible publiquement, affiche uniquement les types actifs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Accessible à tous - aucune vérification d'autorisation nécessaire
            $paymentTypes = $this->paymentTypeService->getActivePaymentTypes();

            return response()->json([
                'payment_types' => $paymentTypes
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération types de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des types de paiement'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher la liste complète des types de paiement (actifs et inactifs)
     * Réservé aux administrateurs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexAdmin()
    {
        try {
            // Autorisé par middleware role:admin
            $paymentTypes = $this->paymentTypeService->getAllPaymentTypes();

            return response()->json([
                'payment_types' => $paymentTypes
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération admin types de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des types de paiement'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les détails d'un type de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $paymentType = $this->paymentTypeService->getPaymentTypeById($id);

            // Vérification d'autorisation spécifique:
            // Si type inactif, seuls les admins peuvent y accéder
            if (!$paymentType->is_active) {
                $user = auth('api')->user();
                if (!$user || !$user->hasRole('admin')) {
                    return response()->json([
                        'error' => 'Type de paiement non disponible'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            return response()->json([
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Type de paiement introuvable'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Créer un nouveau type de paiement
     *
     * @param StorePaymentTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StorePaymentTypeRequest $request)
    {
        try {
            // Autorisé par middleware role:admin

            // Créer le type de paiement via le service
            $paymentType = $this->paymentTypeService->createPaymentType($request->validated());

            return response()->json([
                'message' => 'Type de paiement créé avec succès',
                'payment_type' => $paymentType
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            LogFacade::error('Erreur création type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la création du type de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour un type de paiement existant
     *
     * @param UpdatePaymentTypeRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePaymentTypeRequest $request, $id)
    {
        try {
            // Autorisé par middleware role:admin

            // Mettre à jour le type de paiement via le service
            $paymentType = $this->paymentTypeService->updatePaymentType($id, $request->validated());

            return response()->json([
                'message' => 'Type de paiement mis à jour avec succès',
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise à jour du type de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer un type de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Autorisé par middleware role:admin

            // Supprimer le type de paiement via le service
            $this->paymentTypeService->deletePaymentType($id);

            return response()->json([
                'message' => 'Type de paiement supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression du type de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
 * Récupérer les types de paiement supprimés
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function trashed()
{
    try {
        // Autorisé par middleware role:admin

        $trashedPaymentTypes = $this->paymentTypeService->getTrashedPaymentTypes();

        return response()->json([
            'payment_types' => $trashedPaymentTypes
        ]);
    } catch (\Exception $e) {
        LogFacade::error('Erreur récupération types de paiement supprimés: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de la récupération des types de paiement supprimés'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * Restaurer un type de paiement supprimé
 *
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function restore($id)
{
    try {
        // Autorisé par middleware role:admin

        $this->paymentTypeService->restorePaymentType($id);

        return response()->json([
            'message' => 'Type de paiement restauré avec succès'
        ]);
    } catch (\Exception $e) {
        LogFacade::error('Erreur restauration type de paiement: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de la restauration du type de paiement: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * Supprimer définitivement un type de paiement
 *
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function forceDelete($id)
{
    try {
        // Autorisé par middleware role:admin

        $this->paymentTypeService->forceDeletePaymentType($id);

        return response()->json([
            'message' => 'Type de paiement supprimé définitivement avec succès'
        ]);
    } catch (\Exception $e) {
        LogFacade::error('Erreur suppression définitive type de paiement: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de la suppression définitive du type de paiement: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Activer un type de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate($id)
    {
        try {
            // Autorisé par middleware role:admin

            $paymentType = $this->paymentTypeService->activatePaymentType($id);

            return response()->json([
                'message' => 'Type de paiement activé avec succès',
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur activation type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de l\'activation du type de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Désactiver un type de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate($id)
    {
        try {
            // Autorisé par middleware role:admin

            $paymentType = $this->paymentTypeService->deactivatePaymentType($id);

            return response()->json([
                'message' => 'Type de paiement désactivé avec succès',
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur désactivation type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la désactivation du type de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Basculer le statut d'un type de paiement (activer/désactiver)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        try {
            // Autorisé par middleware role:admin

            $paymentType = $this->paymentTypeService->togglePaymentTypeStatus($id);

            $status = $paymentType->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'message' => 'Type de paiement ' . $status . ' avec succès',
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur changement statut type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors du changement de statut du type de paiement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour la configuration d'un type de paiement
     *
     * @param UpdateConfigRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateConfig(UpdateConfigRequest $request, $id)
    {
        try {
            // Autorisé par middleware role:admin

            // Mettre à jour la configuration via le service
            $paymentType = $this->paymentTypeService->updatePaymentTypeConfig($id, $request->validated()['config']);

            return response()->json([
                'message' => 'Configuration du type de paiement mise à jour avec succès',
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour config type de paiement: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise à jour de la configuration: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Configuration spécifique pour Wave
     *
     * @param ConfigureWaveRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function configureWave(ConfigureWaveRequest $request)
    {
        try {
            // Autorisé par middleware role:admin

            // Configurer Wave en utilisant le service
            $data = $request->validated();

            // Récupérer ou créer le type de paiement Wave
            $paymentType = $this->paymentTypeService->getPaymentTypeByName('wave');

            if (!$paymentType) {
                // Créer le type de paiement Wave s'il n'existe pas
                $paymentType = $this->paymentTypeService->createPaymentType([
                    'name' => 'wave',
                    'display_name' => 'Wave',
                    'description' => 'Paiement via Wave Money Transfer',
                    'icon' => 'images/payment-types/wave.png',
                    'is_active' => true,
                    'config' => [
                        'api_key' => $data['api_key'],
                        'api_secret' => $data['api_secret'],
                        'webhook_url' => route('webhooks.wave'),
                        'merchant_id' => $data['merchant_id'],
                        'environment' => $data['environment']
                    ]
                ]);

                $message = 'Configuration Wave créée avec succès';
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config['api_key'] = $data['api_key'];
                $config['api_secret'] = $data['api_secret'];
                $config['webhook_url'] = route('webhooks.wave');
                $config['merchant_id'] = $data['merchant_id'];
                $config['environment'] = $data['environment'];

                $paymentType = $this->paymentTypeService->updatePaymentTypeConfig($paymentType->id, $config);

                $message = 'Configuration Wave mise à jour avec succès';
            }

            return response()->json([
                'message' => $message,
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur configuration Wave: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la configuration de Wave: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Configuration spécifique pour Orange Money
     *
     * @param ConfigureOrangeMoneyRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function configureOrangeMoney(ConfigureOrangeMoneyRequest $request)
    {
        try {
            // Autorisé par middleware role:admin

            // Configurer Orange Money en utilisant le service
            $data = $request->validated();

            // Récupérer ou créer le type de paiement Orange Money
            $paymentType = $this->paymentTypeService->getPaymentTypeByName('orange_money');

            if (!$paymentType) {
                // Créer le type de paiement Orange Money s'il n'existe pas
                $paymentType = $this->paymentTypeService->createPaymentType([
                    'name' => 'orange_money',
                    'display_name' => 'Orange Money',
                    'description' => 'Paiement via Orange Money',
                    'icon' => 'images/payment-types/orange-money.png',
                    'is_active' => true,
                    'config' => [
                        'api_key' => $data['api_key'],
                        'api_secret' => $data['api_secret'],
                        'callback_url' => route('webhooks.orange_money'),
                        'merchant_id' => $data['merchant_id'],
                        'environment' => $data['environment']
                    ]
                ]);

                $message = 'Configuration Orange Money créée avec succès';
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config['api_key'] = $data['api_key'];
                $config['api_secret'] = $data['api_secret'];
                $config['callback_url'] = route('webhooks.orange_money');
                $config['merchant_id'] = $data['merchant_id'];
                $config['environment'] = $data['environment'];

                $paymentType = $this->paymentTypeService->updatePaymentTypeConfig($paymentType->id, $config);

                $message = 'Configuration Orange Money mise à jour avec succès';
            }

            return response()->json([
                'message' => $message,
                'payment_type' => $paymentType
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur configuration Orange Money: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la configuration d\'Orange Money: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
