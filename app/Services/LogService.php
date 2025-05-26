<?php

namespace App\Services;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\Log as LogFacade;

class LogService
{
    /**
     * Récupérer tous les logs avec pagination et filtres (admin uniquement)
     *
     * @param array $filters
     * @return array
     */
    public function getLogs(array $filters = [])
    {
        try {
            // Construire la requête de base
            $query = Log::with('user');

            // Filtrer par utilisateur spécifique si demandé
            if (isset($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }

            // Appliquer les filtres
            $query = $this->applyFilters($query, $filters);

            // Pagination
            $perPage = $filters['per_page'] ?? 20;
            $page = $filters['page'] ?? 1;

            $logs = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'logs' => $logs
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur getLogs: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des logs',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Créer un nouveau log
     *
     * @param array $data
     * @return array
     */
    public function createLog(array $data)
    {
        try {
            // Remplir automatiquement certaines informations si elles ne sont pas fournies
            if (!isset($data['user_id']) && Auth::check()) {
                $data['user_id'] = Auth::id();
            }

            if (!isset($data['ip_address'])) {
                $data['ip_address'] = RequestFacade::ip();
            }

            if (!isset($data['user_agent'])) {
                $data['user_agent'] = RequestFacade::userAgent();
            }

            // Créer le log
            $log = Log::create($data);

            return [
                'success' => true,
                'log' => $log
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur createLog: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du log',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Supprimer un log (accessible uniquement aux admins)
     *
     * @param int $id
     * @return array
     */
    public function deleteLog(int $id)
    {
        try {
            $log = Log::findOrFail($id);
            $log->delete();

            return [
                'success' => true,
                'message' => 'Log supprimé avec succès'
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur deleteLog: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du log',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Purger les logs plus anciens qu'une certaine date
     *
     * @param string $date Format Y-m-d
     * @return array
     */
    public function purgeLogs(string $date)
    {
        try {
            $count = Log::where('created_at', '<', $date . ' 00:00:00')->delete();

            return [
                'success' => true,
                'message' => $count . ' logs ont été purgés avec succès',
                'count' => $count
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur purgeLogs: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la purge des logs',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Appliquer les filtres à la requête de logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyFilters($query, array $filters)
    {
        // Filtrer par action
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Filtrer par plage de dates
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Filtrer par adresse IP
        if (isset($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        // Filtrer par type d'entité
        if (isset($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        // Filtrer par ID d'entité
        if (isset($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        // Recherche textuelle dans la description
        if (isset($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        // Tri
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $query->orderBy($sortBy, $sortDir);

        return $query;
    }
}
