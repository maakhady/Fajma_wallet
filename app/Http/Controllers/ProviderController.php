<?php

namespace App\Http\Controllers;

use App\Http\Requests\Provider\CreateProviderRequest;
use App\Http\Requests\Provider\UpdateProviderRequest;
use App\Models\Provider;
use App\Services\ProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;


class ProviderController extends Controller
{
    protected $providerService;

    /**
     * Constructeur avec injection du service et middleware d'authentification
     *
     * @param ProviderService $providerService
     */
    public function __construct(ProviderService $providerService)
    {
        $this->providerService = $providerService;

        if (method_exists($this, 'middleware')) {
            // Routes publiques
            $this->middleware('auth:api');

            // Routes administrateur
            $this->middleware('role:admin')->except([
                'index', 'show', 'getHealthProviders', 'getFinancialServices'
            ]);
        }
    }

    /**
     * Afficher la liste des prestataires actifs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $type = $request->query('type');

            if ($type) {
                $providers = $this->providerService->getProvidersByType($type);
            } else {
                $providers = $this->providerService->getActiveProviders();
            }

            return response()->json([
                'providers' => $providers
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération prestataires: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des prestataires'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher la liste des prestataires Inactifs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function prestaInactifs(Request $request)
    {
        try {
            $type = $request->query('type');

            if ($type) {
                $providers = $this->providerService->getProvidersByType($type);
            } else {
                $providers = $this->providerService->getInactifsProviders();
            }

            return response()->json([
                'providers' => $providers
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération prestataires: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des prestataires'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher la liste complète des prestataires (actifs, inactifs et en attente)
     * Réservé aux administrateurs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexAdmin()
    {
        try {
            $providers = $this->providerService->getAllProviders();

            return response()->json([
                'providers' => $providers
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération admin prestataires: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des prestataires'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les prestataires de santé
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHealthProviders()
    {
        try {
            $providers = $this->providerService->getProvidersByType('prestataire_sante');

            return response()->json([
                'health_providers' => $providers
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération prestataires de santé: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des prestataires de santé'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les services financiers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFinancialServices()
    {
        try {
            $providers = $this->providerService->getProvidersByType('service_finance');

            return response()->json([
                'financial_services' => $providers
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération services financiers: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des services financiers'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les détails d'un prestataire
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $provider = $this->providerService->getProviderById($id);

            // Si le prestataire n'est pas actif et que l'utilisateur n'est pas admin, retourner une erreur
            if ($provider->status !== 'active' && (!auth('api')->check() || auth('api')->user()->role !== 'admin')) {
                return response()->json([
                    'error' => 'Prestataire non disponible'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Prestataire introuvable'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function store(CreateProviderRequest $request)
{
    try {
        $data = $request->safe()->except(['logo', 'logo_base64']);

        // 1) FICHIER uploadé (multipart/form-data)
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('providers/logos', 'public');
            $data['logo'] = Storage::disk('public')->url($path);
        }
        // 2) OU base64
        elseif ($request->filled('logo_base64')) {
            $data['logo'] = $this->saveBase64Image(
                $request->input('logo_base64'),
                'providers/logos'
            );
        }

        $provider = $this->providerService->createProvider($data);

        return response()->json([
            'message'  => 'Prestataire créé avec succès',
            'provider' => $provider
        ], Response::HTTP_CREATED);

    } catch (\Throwable $e) {
        \Log::error('Erreur création prestataire: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'error' => 'Erreur lors de la création du prestataire'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

// Méthode privée pour stocker une image base64
private function saveBase64Image(string $base64, string $dir): string
{
    // data:image/png;base64,XXXX
    [$meta, $content] = explode(',', $base64, 2);
    // extension
    preg_match('/^data:image\/(?<ext>png|jpe?g|gif|svg\+xml|webp);base64$/', $meta, $m);
    $ext = $m['ext'] ?? 'png';
    if ($ext === 'svg+xml') $ext = 'svg';

    $binary = base64_decode($content);
    $filename = $dir.'/'.uniqid('logo_', true).'.'.$ext;

    Storage::disk('public')->put($filename, $binary);

    return Storage::disk('public')->url($filename);
}

    /**
     * Mettre à jour un prestataire existant
     *
     * @param UpdateProviderRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProviderRequest $request, $id)
    {
        try {
            $provider = $this->providerService->updateProvider($id, $request->validated());

            return response()->json([
                'message' => 'Prestataire mis à jour avec succès',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise à jour du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer un prestataire (soft delete)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $this->providerService->deleteProvider($id);

            return response()->json([
                'message' => 'Prestataire supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activer un prestataire
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate($id)
    {
        try {
            $provider = $this->providerService->activateProvider($id);

            return response()->json([
                'message' => 'Prestataire activé avec succès',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur activation prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de l\'activation du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Désactiver un prestataire
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate($id)
    {
        try {
            $provider = $this->providerService->deactivateProvider($id);

            return response()->json([
                'message' => 'Prestataire désactivé avec succès',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur désactivation prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la désactivation du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre en attente un prestataire
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function pending($id)
    {
        try {
            $provider = $this->providerService->pendingProvider($id);

            return response()->json([
                'message' => 'Prestataire mis en attente avec succès',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise en attente prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise en attente du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Mettre à jour le taux de commission d'un prestataire
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCommission(Request $request, $id)
    {
        try {
            // Valider le taux de commission
            $request->validate([
                'commission_rate' => 'required|numeric|min:0|max:100'
            ]);

            $provider = $this->providerService->updateCommissionRate($id, $request->commission_rate);

            return response()->json([
                'message' => 'Taux de commission mis à jour avec succès',
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur mise à jour commission: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la mise à jour du taux de commission: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les prestataires supprimés
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function trashed()
    {
        try {
            $providers = $this->providerService->getTrashedProviders();

            return response()->json([
                'trashed_providers' => $providers
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération prestataires supprimés: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des prestataires supprimés'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restaurer un prestataire supprimé
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            $this->providerService->restoreProvider($id);

            return response()->json([
                'message' => 'Prestataire restauré avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur restauration prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la restauration du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer définitivement un prestataire
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete($id)
    {
        try {
            $this->providerService->forceDeleteProvider($id);

            return response()->json([
                'message' => 'Prestataire supprimé définitivement avec succès'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur suppression définitive prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la suppression définitive du prestataire: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Récupérer l'historique d'un prestataire
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */


    public function history($id)
    {
        try {
            $history = $this->providerService->getProviderHistory($id);

            return response()->json([
                'history' => $history
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur récupération historique prestataire: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération de l\'historique du prestataire'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
