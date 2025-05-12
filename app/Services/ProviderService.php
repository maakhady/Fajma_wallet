<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log as LogFacade;

class ProviderService
{
    /**
     * Récupérer tous les prestataires
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllProviders()
    {
        try {
            return Provider::all();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des prestataires: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer uniquement les prestataires actifs
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveProviders()
    {
        try {
            return Provider::where('status', 'active')->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des prestataires actifs: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer les prestataires par type
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProvidersByType($type)
    {
        try {
            return Provider::where('provider_type', $type)->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des prestataires par type: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un prestataire par son ID
     *
     * @param int $id
     * @return Provider
     */
    public function getProviderById($id)
    {
        try {
            return Provider::findOrFail($id);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Créer un nouveau prestataire
     *
     * @param array $data
     * @return Provider
     */
    public function createProvider(array $data)
    {
        try {
            // Traiter le logo si fourni
            if (isset($data['logo']) && $data['logo']) {
                $logoPath = $this->storeLogo($data['logo']);
                $data['logo'] = $logoPath;
            }

            // Créer le prestataire
            $provider = Provider::create($data);

            // Journaliser la création
            $this->logAction('create_provider', $provider->id, 'Création du prestataire: ' . $provider->structure_name);

            return $provider;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la création du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un prestataire existant
     *
     * @param int $id
     * @param array $data
     * @return Provider
     */
    public function updateProvider($id, array $data)
    {
        try {
            $provider = $this->getProviderById($id);

            // Traiter le logo si fourni
            if (isset($data['logo']) && $data['logo']) {
                // Supprimer l'ancien logo s'il existe
                if ($provider->logo) {
                    Storage::delete($provider->logo);
                }

                $logoPath = $this->storeLogo($data['logo']);
                $data['logo'] = $logoPath;
            }

            // Mettre à jour le prestataire
            $provider->update($data);

            // Journaliser la mise à jour
            $this->logAction('update_provider', $provider->id, 'Mise à jour du prestataire: ' . $provider->structure_name);

            return $provider;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer un prestataire (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function deleteProvider($id)
    {
        try {
            $provider = $this->getProviderById($id);

            // Vérifier si le prestataire a des transactions
            if ($provider->transactions()->count() > 0) {
                throw new \Exception('Ce prestataire est associé à des transactions et ne peut pas être supprimé.');
            }

            // Journaliser la suppression avant de supprimer
            $this->logAction('delete_provider', $provider->id, 'Suppression du prestataire: ' . $provider->structure_name);

            // Soft delete
            return $provider->delete();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la suppression du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activer un prestataire
     *
     * @param int $id
     * @return Provider
     */
    public function activateProvider($id)
    {
        try {
            $provider = $this->getProviderById($id);
            $provider->status = 'active';
            $provider->save();

            // Journaliser l'activation
            $this->logAction('activate_provider', $provider->id, 'Activation du prestataire: ' . $provider->structure_name);

            return $provider;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de l\'activation du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Désactiver un prestataire
     *
     * @param int $id
     * @return Provider
     */
    public function deactivateProvider($id)
    {
        try {
            $provider = $this->getProviderById($id);
            $provider->status = 'inactive';
            $provider->save();

            // Journaliser la désactivation
            $this->logAction('deactivate_provider', $provider->id, 'Désactivation du prestataire: ' . $provider->structure_name);

            return $provider;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la désactivation du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre en attente un prestataire
     *
     * @param int $id
     * @return Provider
     */
    public function pendingProvider($id)
    {
        try {
            $provider = $this->getProviderById($id);
            $provider->status = 'pending';
            $provider->save();

            // Journaliser la mise en attente
            $this->logAction('pending_provider', $provider->id, 'Mise en attente du prestataire: ' . $provider->structure_name);

            return $provider;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise en attente du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Modifier le taux de commission d'un prestataire
     *
     * @param int $id
     * @param float $rate
     * @return Provider
     */
    public function updateCommissionRate($id, float $rate)
    {
        try {
            $provider = $this->getProviderById($id);

            // Vérification du taux de commission
            if ($rate < 0 || $rate > 100) {
                throw new \Exception('Le taux de commission doit être compris entre 0 et 100.');
            }

            $provider->commission_rate = $rate;
            $provider->save();

            // Journaliser la modification du taux de commission
            $this->logAction('update_commission_rate', $provider->id, 'Modification du taux de commission du prestataire: ' . $provider->structure_name . ' (' . $rate . '%)');

            return $provider;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la modification du taux de commission: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer les prestataires supprimés
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrashedProviders()
    {
        try {
            return Provider::onlyTrashed()->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des prestataires supprimés: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurer un prestataire supprimé
     *
     * @param int $id
     * @return bool
     */
    public function restoreProvider($id)
    {
        try {
            $provider = Provider::withTrashed()->findOrFail($id);

            // Journaliser la restauration
            $this->logAction('restore_provider', $provider->id, 'Restauration du prestataire: ' . $provider->structure_name);

            return $provider->restore();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la restauration du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer définitivement un prestataire
     *
     * @param int $id
     * @return bool
     */
    public function forceDeleteProvider($id)
    {
        try {
            $provider = Provider::withTrashed()->findOrFail($id);

            // Vérifier si le prestataire a des transactions
            if ($provider->transactions()->count() > 0) {
                throw new \Exception('Ce prestataire est associé à des transactions et ne peut pas être supprimé définitivement.');
            }

            // Supprimer le logo si existant
            if ($provider->logo) {
                Storage::delete($provider->logo);
            }

            // Journaliser la suppression définitive
            $this->logAction('force_delete_provider', $provider->id, 'Suppression définitive du prestataire: ' . $provider->structure_name);

            // Suppression définitive
            return $provider->forceDelete();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la suppression définitive du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stocker le logo du prestataire
     *
     * @param \Illuminate\Http\UploadedFile $logo
     * @return string
     */
    private function storeLogo($logo)
    {
        $fileName = time() . '_' . str_replace(' ', '_', $logo->getClientOriginalName());
        $path = $logo->storeAs('images/providers', $fileName, 'public');
        return $path;
    }

    /**
     * Journaliser une action
     *
     * @param string $action
     * @param int $entityId
     * @param string $description
     * @return void
     */
    private function logAction($action, $entityId, $description)
    {
        try {
            Log::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'entity_type' => 'provider',
                'entity_id' => $entityId,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la journalisation: ' . $e->getMessage());
        }
    }


     /**
     * l'historique d'un prestataire
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProviderHistory($id)
    {
        try {
            $provider = $this->getProviderById($id);
            return $provider->logs()->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération de l\'historique du prestataire: ' . $e->getMessage());
            throw $e;
        }
    }

}
