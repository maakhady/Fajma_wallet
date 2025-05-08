<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use App\Services\PaymentTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\Response;

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
        
        // Appliquer le middleware d'authentification et de vérification du rôle admin
        // pour toutes les méthodes sauf index et show qui sont publiques
        if (method_exists($this, 'middleware')) {
            $this->middleware('auth:api')->except(['index', 'show']);
            $this->middleware('role:admin')->except(['index', 'show']);
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
            // Récupérer les types de paiement actifs
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
            // Récupérer tous les types de paiement
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
            
            // Si le type de paiement n'est pas actif et que l'utilisateur n'est pas admin, retourner une erreur
            if (!$paymentType->is_active && (!auth('api')->check() || auth('api')->user()->role !== 'admin')) {
                return response()->json([
                    'error' => 'Type de paiement non disponible'
                ], Response::HTTP_NOT_FOUND);
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Valider les données entrantes
            $request->validate([
                'name' => 'required|string|unique:payment_types,name',
                'display_name' => 'required|string',
                'description' => 'nullable|string',
                'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                'is_active' => 'boolean',
                'config' => 'nullable|array'
            ]);
            
            // Créer le type de paiement via le service
            $paymentType = $this->paymentTypeService->createPaymentType($request->all());
            
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
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Valider les données entrantes
            $request->validate([
                'name' => 'string|unique:payment_types,name,' . $id,
                'display_name' => 'string',
                'description' => 'nullable|string',
                'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                'is_active' => 'boolean',
                'config' => 'nullable|array'
            ]);
            
            // Mettre à jour le type de paiement via le service
            $paymentType = $this->paymentTypeService->updatePaymentType($id, $request->all());
            
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
     * Activer un type de paiement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate($id)
    {
        try {
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
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateConfig(Request $request, $id)
    {
        try {
            // Valider les données entrantes
            $request->validate([
                'config' => 'required|array'
            ]);
            
            // Mettre à jour la configuration via le service
            $paymentType = $this->paymentTypeService->updatePaymentTypeConfig($id, $request->config);
            
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function configureWave(Request $request)
    {
        try {
            // Valider les données entrantes
            $request->validate([
                'api_key' => 'required|string',
                'api_secret' => 'required|string',
                'merchant_id' => 'required|string',
                'environment' => 'required|in:sandbox,production'
            ]);
            
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
                        'api_key' => $request->api_key,
                        'api_secret' => $request->api_secret,
                        'webhook_url' => route('webhooks.wave'),
                        'merchant_id' => $request->merchant_id,
                        'environment' => $request->environment
                    ]
                ]);
                
                $message = 'Configuration Wave créée avec succès';
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config['api_key'] = $request->api_key;
                $config['api_secret'] = $request->api_secret;
                $config['webhook_url'] = route('webhooks.wave');
                $config['merchant_id'] = $request->merchant_id;
                $config['environment'] = $request->environment;
                
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function configureOrangeMoney(Request $request)
    {
        try {
            // Valider les données entrantes
            $request->validate([
                'api_key' => 'required|string',
                'api_secret' => 'required|string',
                'merchant_id' => 'required|string',
                'environment' => 'required|in:sandbox,production'
            ]);
            
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
                        'api_key' => $request->api_key,
                        'api_secret' => $request->api_secret,
                        'callback_url' => route('webhooks.orange_money'),
                        'merchant_id' => $request->merchant_id,
                        'environment' => $request->environment
                    ]
                ]);
                
                $message = 'Configuration Orange Money créée avec succès';
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config['api_key'] = $request->api_key;
                $config['api_secret'] = $request->api_secret;
                $config['callback_url'] = route('webhooks.orange_money');
                $config['merchant_id'] = $request->merchant_id;
                $config['environment'] = $request->environment;
                
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