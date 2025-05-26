<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Services\LogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;

class LogController extends Controller
{
    protected $logService;

    /**
     * Constructeur
     *
     * @param LogService $logService
     */
    public function __construct(LogService $logService)
    {
        $this->logService = $logService;

        // Appliquer le middleware d'authentification à toutes les méthodes
        $this->middleware('auth:api');

        // Vérifier si l'utilisateur est admin pour toutes les méthodes
        $this->middleware(function ($request, $next) {
            if (!$this->isAdmin()) {
                return response()->json([
                    'error' => 'Non autorisé. Droits administrateur requis.'
                ], Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        });
    }

    /**
     * Vérifier si l'utilisateur est administrateur
     *
     * @return bool
     */
    protected function isAdmin()
    {
        return Auth::user()->isAdmin();
    }

    /**
     * Récupérer tous les logs (administrateurs uniquement)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();

            $result = $this->logService->getLogs($filters, true);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json($result['logs']);
        } catch (\Exception $e) {
            LogFacade::error('Erreur index logs: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération des logs',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher un log spécifique (administrateurs uniquement)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Récupérer le log avec ses relations
            $log = Log::with('user')->findOrFail($id);

            return response()->json($log);
        } catch (\Exception $e) {
            LogFacade::error('Erreur show log: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération du log',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer un log (administrateurs uniquement)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {
            $result = $this->logService->deleteLog($id);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur delete log: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la suppression du log',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Purger les logs plus anciens qu'une certaine date (administrateurs uniquement)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purge(Request $request)
    {
        try {
            // Valider la requête
            $request->validate([
                'date' => 'required|date_format:Y-m-d'
            ]);

            $result = $this->logService->purgeLogs($request->input('date'));

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'message' => $result['message'],
                'count' => $result['count']
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur purge logs: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la purge des logs',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les actions disponibles pour le filtrage (administrateurs uniquement)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActions()
    {
        try {
            // Récupérer toutes les actions distinctes des logs
            $actions = Log::distinct()->pluck('action');

            return response()->json($actions);
        } catch (\Exception $e) {
            LogFacade::error('Erreur getActions: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération des actions',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les types d'entités disponibles pour le filtrage (administrateurs uniquement)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEntityTypes()
    {
        try {
            // Récupérer tous les types d'entités distincts des logs
            $entityTypes = Log::whereNotNull('entity_type')
                             ->distinct()
                             ->pluck('entity_type');

            return response()->json($entityTypes);
        } catch (\Exception $e) {
            LogFacade::error('Erreur getEntityTypes: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération des types d\'entités',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les utilisateurs ayant des logs (administrateurs uniquement)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers()
    {
        try {
            // Récupérer tous les utilisateurs distincts ayant des logs
            $userIds = Log::whereNotNull('user_id')
                         ->distinct()
                         ->pluck('user_id');

            $users = \App\Models\User::whereIn('id', $userIds)
                                  ->select('id', 'name', 'email')
                                  ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            LogFacade::error('Erreur getUsers: ' . $e->getMessage());

            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération des utilisateurs',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
