<?php

namespace App\Services;

use App\Models\PaymentMean;
use App\Models\PaymentType;
use App\Models\User;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

class PaymentMeanService
{
    /**
     * Récupérer tous les moyens de paiement
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPaymentMeans()
    {
        try {
            return PaymentMean::with('paymentType', 'user')->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des moyens de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer tous les moyens de paiement d'un utilisateur
     *
     * @param int $userId
     * @param bool $onlyActive Récupérer uniquement les moyens de paiement actifs
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserPaymentMeans($userId, $onlyActive = false)
    {
        try {
            $query = PaymentMean::with('paymentType')->where('user_id', $userId);

            if ($onlyActive) {
                $query->where('status', 'active');
            }

            return $query->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des moyens de paiement de l\'utilisateur: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer le moyen de paiement par défaut d'un utilisateur
     *
     * @param int $userId
     * @return PaymentMean|null
     */
    public function getUserDefaultPaymentMean($userId)
    {
        try {
            return PaymentMean::with('paymentType')
                ->where('user_id', $userId)
                ->where('is_default', true)
                ->where('status', 'active')
                ->first();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du moyen de paiement par défaut: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un moyen de paiement par son ID
     *
     * @param int $id
     * @return PaymentMean
     */
    public function getPaymentMeanById($id)
    {
        try {
            return PaymentMean::with('paymentType', 'user')->findOrFail($id);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Créer un nouveau moyen de paiement
     *
     * @param array $data
     * @return PaymentMean
     */
    public function createPaymentMean(array $data)
    {
        try {
            // Vérifier que le type de paiement existe et est actif
            $paymentType = PaymentType::findOrFail($data['payment_type_id']);
            if (!$paymentType->is_active) {
                throw new \Exception('Ce type de paiement n\'est pas actif actuellement.');
            }

            // Vérifier que l'utilisateur existe
            $user = User::findOrFail($data["user_id"]);

            // Valider l'identifiant de compte si le type de paiement est Orange Money
            if ($paymentType->name === 'orange_money') { // Assurez-vous que 'orange_money' est le nom correct du type de paiement
                if (!$this->validateOrangeMoneyIdentifier($data['account_identifier'])) {
                    throw new \Exception('L\'identifiant de compte Orange Money n\'est pas valide.');
                }
            }

            // Chiffrer l'identifiant du compte
            $data['account_identifier'] = Crypt::encryptString($data['account_identifier']);

            // Définir la date de liaison
            $data['linked_date'] = Carbon::now();

            // Si c'est le premier moyen de paiement de l'utilisateur, le définir par défaut
            if (!isset($data['is_default'])) {
                $existingPaymentMeans = $this->getUserPaymentMeans($data['user_id'], true);
                $data['is_default'] = $existingPaymentMeans->isEmpty();
            }

            // Si ce moyen de paiement est défini comme par défaut, désactiver les autres
            if ($data['is_default']) {
                $this->resetDefaultPaymentMeans($data['user_id']);
            }

            // Créer le moyen de paiement
            $paymentMean = PaymentMean::create($data);

            // Charger les relations
            $paymentMean->load('paymentType', 'user');

            // Journaliser la création
            $this->logAction('create_payment_mean', $paymentMean->id, 'Création du moyen de paiement ' . $paymentType->display_name . ' pour ' . $user->first_name . ' ' . $user->last_name);

            return $paymentMean;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la création du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un moyen de paiement existant
     *
     * @param int $id
     * @param array $data
     * @return PaymentMean
     */
    public function updatePaymentMean($id, array $data)
    {
        try {
            $paymentMean = $this->getPaymentMeanById($id);

            // Si le type de paiement change, vérifier qu'il est actif
            if (isset($data['payment_type_id']) && $data['payment_type_id'] != $paymentMean->payment_type_id) {
                $paymentType = PaymentType::findOrFail($data['payment_type_id']);
                if (!$paymentType->is_active) {
                    throw new \Exception('Ce type de paiement n\'est pas actif actuellement.');
                }
            }

            // Chiffrer l'identifiant du compte s'il est modifié
            if (isset($data["account_identifier"])) {
                // Si le type de paiement est Orange Money, valider l'identifiant
                $paymentType = $paymentMean->paymentType; // Utiliser le type de paiement existant ou le nouveau si modifié
                if (isset($data["payment_type_id"]) && $data["payment_type_id"] != $paymentMean->payment_type_id) {
                    $paymentType = PaymentType::findOrFail($data["payment_type_id"]);
                }

                if ($paymentType->name === 'orange_money') { // Assurez-vous que 'orange_money' est le nom correct du type de paiement
                    if (!$this->validateOrangeMoneyIdentifier($data["account_identifier"])) {
                        throw new \Exception('L\'identifiant de compte Orange Money n\'est pas valide.');
                    }
                }
                $data["account_identifier"] = Crypt::encryptString($data["account_identifier"]);
            }

            // Si ce moyen de paiement devient le moyen par défaut
            if (isset($data['is_default']) && $data['is_default'] && !$paymentMean->is_default) {
                $this->resetDefaultPaymentMeans($paymentMean->user_id);
            }

            // Mettre à jour le moyen de paiement
            $paymentMean->update($data);

            // Recharger les relations
            $paymentMean->load('paymentType', 'user');

            // Journaliser la mise à jour
            $this->logAction('update_payment_mean', $paymentMean->id, 'Mise à jour du moyen de paiement ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            return $paymentMean;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer un moyen de paiement (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function deletePaymentMean($id)
    {
        try {
            $paymentMean = $this->getPaymentMeanById($id);

            // Vérifier si c'est le moyen de paiement par défaut
            if ($paymentMean->is_default) {
                // Trouver un autre moyen de paiement actif à définir comme par défaut
                $anotherPaymentMean = PaymentMean::where('user_id', $paymentMean->user_id)
                    ->where('id', '!=', $paymentMean->id)
                    ->where('status', 'active')
                    ->first();

                if ($anotherPaymentMean) {
                    $anotherPaymentMean->is_default = true;
                    $anotherPaymentMean->save();
                }
            }

            // Journaliser la suppression
            $this->logAction('delete_payment_mean', $paymentMean->id, 'Suppression du moyen de paiement ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            // Soft delete
            return $paymentMean->delete();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la suppression du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer les moyens de paiement supprimés
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrashedPaymentMeans()
    {
        try {
            return PaymentMean::onlyTrashed()->with('paymentType', 'user')->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des moyens de paiement supprimés: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurer un moyen de paiement supprimé
     *
     * @param int $id
     * @return PaymentMean
     */
    public function restorePaymentMean($id)
    {
        try {
            $paymentMean = PaymentMean::onlyTrashed()->with('paymentType', 'user')->findOrFail($id);
            $paymentMean->restore();

            // Journaliser la restauration
            $this->logAction('restore_payment_mean', $paymentMean->id, 'Restauration du moyen de paiement ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            return $paymentMean;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la restauration du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer définitivement un moyen de paiement
     *
     * @param int $id
     * @return bool
     */
    public function forceDeletePaymentMean($id)
    {
        try {
            $paymentMean = PaymentMean::withTrashed()->with('paymentType', 'user')->findOrFail($id);

            // Vérifier si le moyen de paiement est associé à des transactions
            if ($paymentMean->transactions()->count() > 0) {
                throw new \Exception('Ce moyen de paiement est associé à des transactions et ne peut pas être supprimé définitivement.');
            }

            // Journaliser la suppression définitive
            $this->logAction('force_delete_payment_mean', $paymentMean->id, 'Suppression définitive du moyen de paiement ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            // Suppression définitive
            return $paymentMean->forceDelete();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la suppression définitive du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activer un moyen de paiement
     *
     * @param int $id
     * @return PaymentMean
     */
    public function activatePaymentMean($id)
    {
        try {
            $paymentMean = $this->getPaymentMeanById($id);

            if ($paymentMean->status === 'active') {
                return $paymentMean; // Déjà actif
            }

            $paymentMean->status = 'active';
            $paymentMean->save();

            // Journaliser l'activation
            $this->logAction('activate_payment_mean', $paymentMean->id, 'Activation du moyen de paiement ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            return $paymentMean;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de l\'activation du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Désactiver un moyen de paiement
     *
     * @param int $id
     * @return PaymentMean
     */
    public function deactivatePaymentMean($id)
    {
        try {
            $paymentMean = $this->getPaymentMeanById($id);

            if ($paymentMean->status === 'inactive') {
                return $paymentMean; // Déjà inactif
            }

            // Si c'est le moyen de paiement par défaut
            if ($paymentMean->is_default) {
                // Trouver un autre moyen de paiement actif à définir par défaut
                $anotherPaymentMean = PaymentMean::where('user_id', $paymentMean->user_id)
                    ->where('id', '!=', $paymentMean->id)
                    ->where('status', 'active')
                    ->first();

                if ($anotherPaymentMean) {
                    $anotherPaymentMean->is_default = true;
                    $anotherPaymentMean->save();
                    $paymentMean->is_default = false;
                } else {
                    throw new \Exception('Impossible de désactiver le seul moyen de paiement actif par défaut.');
                }
            }

            $paymentMean->status = 'inactive';
            $paymentMean->save();

            // Journaliser la désactivation
            $this->logAction('deactivate_payment_mean', $paymentMean->id, 'Désactivation du moyen de paiement ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            return $paymentMean;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la désactivation du moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Définir un moyen de paiement comme moyen par défaut
     *
     * @param int $id
     * @return PaymentMean
     */
    public function setAsDefault($id)
    {
        try {
            $paymentMean = $this->getPaymentMeanById($id);

            // Vérifier que le moyen de paiement est actif
            if ($paymentMean->status !== 'active') {
                throw new \Exception('Impossible de définir comme par défaut un moyen de paiement inactif.');
            }

            // Si déjà par défaut, ne rien faire
            if ($paymentMean->is_default) {
                return $paymentMean;
            }

            // Réinitialiser tous les autres moyens de paiement de l'utilisateur
            $this->resetDefaultPaymentMeans($paymentMean->user_id);

            // Définir celui-ci comme moyen par défaut
            $paymentMean->is_default = true;
            $paymentMean->save();

            // Journaliser l'action
            $this->logAction('set_default_payment_mean', $paymentMean->id, 'Définition comme moyen de paiement par défaut du ' . $paymentMean->paymentType->display_name . ' pour ' . $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name);

            return $paymentMean;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la définition du moyen de paiement par défaut: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Compte le nombre de transactions pour chaque moyen de paiement
     *
     * @return array
     */
    public function getTransactionsCountByPaymentMean()
    {
        try {
            $result = [];
            $paymentMeans = PaymentMean::with('paymentType', 'user')->get();

            foreach ($paymentMeans as $paymentMean) {
                $result[] = [
                    'payment_mean_id' => $paymentMean->id,
                    'payment_type' => $paymentMean->paymentType->display_name,
                    'user_name' => $paymentMean->user->first_name . ' ' . $paymentMean->user->last_name,
                    'account_identifier' => $this->maskAccountIdentifier($paymentMean->account_identifier),
                    'status' => $paymentMean->status,
                    'is_default' => $paymentMean->is_default,
                    'transactions_count' => $paymentMean->transactions()->count()
                ];
            }

            return $result;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors du comptage des transactions par moyen de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Réinitialiser tous les moyens de paiement par défaut d'un utilisateur
     *
     * @param int $userId
     * @return void
     */
    private function resetDefaultPaymentMeans($userId)
    {
        PaymentMean::where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Masquer l'identifiant de compte pour l'affichage
     *
     * @param string $encryptedIdentifier
     * @return string
     */
    private function maskAccountIdentifier($encryptedIdentifier)
    {
        try {
            $identifier = Crypt::decryptString($encryptedIdentifier);

            // Masquer tous les caractères sauf les 4 derniers
            $length = strlen($identifier);
            if ($length <= 4) {
                return str_repeat('*', $length);
            }

            return str_repeat('*', $length - 4) . substr($identifier, -4);
        } catch (\Exception $e) {
            return '*** Identifiant chiffré ***';
        }
    }

    /**
     * Décrypter l'identifiant de compte (seulement pour l'administrateur ou le propriétaire)
     *
     * @param int $id
     * @return string
     */
    public function decryptAccountIdentifier($id)
    {
        try {
            $paymentMean = $this->getPaymentMeanById($id);
            $currentUser = Auth::user();

            // Vérifier l'autorisation
            if (!$currentUser) {
                throw new \Exception('Utilisateur non authentifié.');
            }

            if ($currentUser->id !== $paymentMean->user_id && $currentUser->role !== 'admin') {
                throw new \Exception('Vous n\'êtes pas autorisé à voir cet identifiant.');
            }

            // Décrypter et retourner l'identifiant
            return Crypt::decryptString($paymentMean->account_identifier);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors du décryptage de l\'identifiant: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifier qu'un identifiant de compte est valide pour un type de paiement
     *
     * @param string $identifier
     * @param int $paymentTypeId
     * @return bool
     */
    public function validateAccountIdentifier($identifier, $paymentTypeId)
    {
        try {
            $paymentType = PaymentType::findOrFail($paymentTypeId);

            // Validation spécifique selon le type de paiement
            switch ($paymentType->name) {
                case 'wave':
                    // Vérifier que c'est un numéro de téléphone au format sénégalais
                    return preg_match('/^(70|75|76|77|78)\d{7}$/', $identifier) === 1;

                case 'orange_money':
                    // Vérifier que c'est un numéro de téléphone Orange
                    return preg_match('/^(77|78)\d{7}$/', $identifier) === 1;

                // Ajouter d'autres cas selon les types de paiement

                default:
                    // Validation par défaut
                    return !empty($identifier);
            }
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la validation de l\'identifiant: ' . $e->getMessage());
            throw $e;
        }
    }


        /**
     * Récupérer les moyens de paiement supprimés logiquement d'un utilisateur
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrashedPaymentMeansByUser($userId)
    {
        try {
            return PaymentMean::onlyTrashed()
                ->with('paymentType')
                ->where('user_id', $userId)
                ->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des moyens de paiement supprimés de l\'utilisateur: ' . $e->getMessage());
            throw $e;
        }
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
                'entity_type' => 'payment_mean',
                'entity_id' => $entityId,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la journalisation: ' . $e->getMessage());
        
    }
}



    /**
     * Valider l'identifiant de compte pour Orange Money (MSISDN).
     *
     * @param string $identifier
     * @return bool
     */
    private function validateOrangeMoneyIdentifier(string $identifier): bool
    {
        // Regex pour les numéros de téléphone Orange Money au Sénégal (77, 78, 76, 70, 75)
        // Adaptez cette regex si les formats de numéros sont différents ou si d'autres pays sont concernés.
        return preg_match("/^(77|78|76|70|75)[0-9]{7}$/", $identifier);
    }

}