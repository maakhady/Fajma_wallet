<?php

namespace App\Services;

use App\Models\PaymentType;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log as LogFacade;

class PaymentTypeService
{
    /**
     * Récupérer tous les types de paiement
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPaymentTypes()
    {
        try {
            return PaymentType::all();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des types de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer uniquement les types de paiement actifs
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActivePaymentTypes()
    {
        try {
            return PaymentType::where('is_active', true)->get();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des types de paiement actifs: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un type de paiement par son ID
     *
     * @param int $id
     * @return PaymentType
     */
    public function getPaymentTypeById($id)
    {
        try {
            return PaymentType::findOrFail($id);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un type de paiement par son nom
     *
     * @param string $name
     * @return PaymentType|null
     */
    public function getPaymentTypeByName($name)
    {
        try {
            return PaymentType::where('name', $name)->first();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du type de paiement par nom: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Créer un nouveau type de paiement
     *
     * @param array $data
     * @return PaymentType
     */
    public function createPaymentType(array $data)
    {
        try {
            // Traiter l'icône si elle est fournie
            if (isset($data['icon']) && $data['icon']) {
                $iconPath = $this->storeIcon($data['icon']);
                $data['icon'] = $iconPath;
            }

            // Créer le type de paiement
            $paymentType = PaymentType::create($data);

            // Journaliser la création
            $this->logAction('create_payment_type', $paymentType->id, 'Création du type de paiement: ' . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la création du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un type de paiement existant
     *
     * @param int $id
     * @param array $data
     * @return PaymentType
     */
    public function updatePaymentType($id, array $data)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);

            // Traiter l'icône si elle est fournie
            if (isset($data['icon']) && $data['icon']) {
                // Supprimer l'ancienne icône si elle existe
                if ($paymentType->icon) {
                    Storage::delete($paymentType->icon);
                }

                $iconPath = $this->storeIcon($data['icon']);
                $data['icon'] = $iconPath;
            }

            // Mettre à jour le type de paiement
            $paymentType->update($data);

            // Journaliser la mise à jour
            $this->logAction('update_payment_type', $paymentType->id, 'Mise à jour du type de paiement: ' . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activer un type de paiement
     *
     * @param int $id
     * @return PaymentType
     */
    public function activatePaymentType($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->is_active = true;
            $paymentType->save();

            // Journaliser l'activation
            $this->logAction('activate_payment_type', $paymentType->id, 'Activation du type de paiement: ' . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de l\'activation du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Désactiver un type de paiement
     *
     * @param int $id
     * @return PaymentType
     */
    public function deactivatePaymentType($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->is_active = false;
            $paymentType->save();

            // Journaliser la désactivation
            $this->logAction('deactivate_payment_type', $paymentType->id, 'Désactivation du type de paiement: ' . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la désactivation du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Basculer le statut d'un type de paiement
     *
     * @param int $id
     * @return PaymentType
     */
    public function togglePaymentTypeStatus($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->is_active = !$paymentType->is_active;
            $paymentType->save();

            // Journaliser le changement de statut
            $status = $paymentType->is_active ? 'activé' : 'désactivé';
            $this->logAction('toggle_payment_type_status', $paymentType->id, 'Statut du type de paiement ' . $status . ': ' . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors du changement de statut du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
 * Supprimer un type de paiement (soft delete)
 *
 * @param int $id
 * @return bool
 */
public function deletePaymentType($id)
{
    try {
        $paymentType = $this->getPaymentTypeById($id);

        // Vérifier si le type de paiement est utilisé par des moyens de paiement
        if ($paymentType->paymentMeans()->count() > 0) {
            throw new \Exception('Ce type de paiement est utilisé par des moyens de paiement et ne peut pas être supprimé.');
        }

        // Le modèle garde l'icône car elle pourrait être nécessaire en cas de restauration
        // Nous ne supprimons donc plus l'icône: Storage::delete($paymentType->icon);

        // Journaliser la suppression avant de supprimer
        $this->logAction('delete_payment_type', $paymentType->id, 'Suppression logique du type de paiement: ' . $paymentType->display_name);

        // Soft delete - cela définit juste deleted_at et conserve la ligne
        return $paymentType->delete();
    } catch (\Exception $e) {
        LogFacade::error('Erreur lors de la suppression du type de paiement: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Restaurer un type de paiement supprimé
 *
 * @param int $id
 * @return bool
 */
public function restorePaymentType($id)
{
    try {
        $paymentType = PaymentType::withTrashed()->findOrFail($id);

        // Journaliser la restauration
        $this->logAction('restore_payment_type', $paymentType->id, 'Restauration du type de paiement: ' . $paymentType->display_name);

        return $paymentType->restore();
    } catch (\Exception $e) {
        LogFacade::error('Erreur lors de la restauration du type de paiement: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Supprimer définitivement un type de paiement
 *
 * @param int $id
 * @return bool
 */
public function forceDeletePaymentType($id)
{
    try {
        $paymentType = PaymentType::withTrashed()->findOrFail($id);

        // Vérifier si le type de paiement est utilisé par des moyens de paiement
        if ($paymentType->paymentMeans()->count() > 0) {
            throw new \Exception('Ce type de paiement est utilisé par des moyens de paiement et ne peut pas être supprimé définitivement.');
        }

        // Supprimer l'icône car maintenant c'est une suppression définitive
        if ($paymentType->icon) {
            Storage::delete($paymentType->icon);
        }

        // Journaliser la suppression définitive
        $this->logAction('force_delete_payment_type', $paymentType->id, 'Suppression définitive du type de paiement: ' . $paymentType->display_name);

        // Suppression définitive
        return $paymentType->forceDelete();
    } catch (\Exception $e) {
        LogFacade::error('Erreur lors de la suppression définitive du type de paiement: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Récupérer les types de paiement supprimés
 *
 * @return \Illuminate\Database\Eloquent\Collection
 */
public function getTrashedPaymentTypes()
{
    try {
        return PaymentType::onlyTrashed()->get();
    } catch (\Exception $e) {
        LogFacade::error('Erreur lors de la récupération des types de paiement supprimés: ' . $e->getMessage());
        throw $e;
    }
}
    /**
     * Mettre à jour la configuration d'un type de paiement
     *
     * @param int $id
     * @param array $config
     * @return PaymentType
     */
    public function updatePaymentTypeConfig($id, array $config)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->config = $config;
            $paymentType->save();

            // Journaliser la mise à jour de la configuration
            $this->logAction('update_payment_type_config', $paymentType->id, 'Mise à jour de la configuration du type de paiement: ' . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour de la configuration du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Configurer le type de paiement Wave
     *
     * @param array $data
     * @return PaymentType
     */
    public function configureWave(array $data)
    {
        try {
            // Récupérer ou créer le type de paiement Wave
            $paymentType = $this->getPaymentTypeByName('wave');

            if (!$paymentType) {
                return $this->createPaymentType([
                    'name' => 'wave',
                    'display_name' => 'Wave',
                    'description' => 'Paiement via Wave Money Transfer',
                    'icon' => 'images/payment-types/wave.png',
                    'is_active' => true,
                    'config' => [
                        'api_key' => $data['api_key'],
                        'api_secret' => $data['api_secret'],
                        'webhook_url' => url('/api/webhooks/wave'),
                        'merchant_id' => $data['merchant_id'],
                        'environment' => $data['environment']
                    ]
                ]);
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config['api_key'] = $data['api_key'];
                $config['api_secret'] = $data['api_secret'];
                $config['webhook_url'] = url('/api/webhooks/wave');
                $config['merchant_id'] = $data['merchant_id'];
                $config['environment'] = $data['environment'];

                return $this->updatePaymentTypeConfig($paymentType->id, $config);
            }
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la configuration de Wave: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Configurer le type de paiement Orange Money
     *
     * @param array $data
     * @return PaymentType
     */
    public function configureOrangeMoney(array $data)
    {
        try {
            // Récupérer ou créer le type de paiement Orange Money
            $paymentType = $this->getPaymentTypeByName('orange_money');

            if (!$paymentType) {
                return $this->createPaymentType([
                    'name' => 'orange_money',
                    'display_name' => 'Orange Money',
                    'description' => 'Paiement via Orange Money',
                    'icon' => 'images/payment-types/orange-money.png',
                    'is_active' => true,
                    'config' => [
                        'api_key' => $data['api_key'],
                        'api_secret' => $data['api_secret'],
                        'callback_url' => url('/api/webhooks/orange-money'),
                        'merchant_id' => $data['merchant_id'],
                        'environment' => $data['environment']
                    ]
                ]);
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config['api_key'] = $data['api_key'];
                $config['api_secret'] = $data['api_secret'];
                $config['callback_url'] = url('/api/webhooks/orange-money');
                $config['merchant_id'] = $data['merchant_id'];
                $config['environment'] = $data['environment'];

                return $this->updatePaymentTypeConfig($paymentType->id, $config);
            }
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la configuration d\'Orange Money: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stocker l'icône du type de paiement
     *
     * @param \Illuminate\Http\UploadedFile $icon
     * @return string
     */
    private function storeIcon($icon)
    {
        $fileName = time() . '_' . str_replace(' ', '_', $icon->getClientOriginalName());
        $path = $icon->storeAs('images/payment-types', $fileName, 'public');
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
                'entity_type' => 'payment_type',
                'entity_id' => $entityId,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la journalisation: ' . $e->getMessage());
        }
    }
}
