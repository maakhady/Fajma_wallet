<?php

namespace App\Services;

use App\Models\PaymentType;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            // Valider les données
            $validator = Validator::make($data, [
                'name' => 'required|string|unique:payment_types,name',
                'display_name' => 'required|string',
                'description' => 'nullable|string',
                'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                'is_active' => 'boolean',
                'config' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                throw new \Exception('Données invalides: ' . json_encode($validator->errors()));
            }

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

            // Valider les données
            $validator = Validator::make($data, [
                'name' => 'string|unique:payment_types,name,' . $id,
                'display_name' => 'string',
                'description' => 'nullable|string',
                'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                'is_active' => 'boolean',
                'config' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                throw new \Exception('Données invalides: ' . json_encode($validator->errors()));
            }

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
     * Supprimer un type de paiement
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

            // Supprimer l'icône si elle existe
            if ($paymentType->icon) {
                Storage::delete($paymentType->icon);
            }

            // Journaliser la suppression avant de supprimer
            $this->logAction('delete_payment_type', $paymentType->id, 'Suppression du type de paiement: ' . $paymentType->display_name);

            // Supprimer le type de paiement
            return $paymentType->delete();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la suppression du type de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer la configuration d'un type de paiement
     *
     * @param int $id
     * @return array|null
     */
    public function getPaymentTypeConfig($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            return $paymentType->config;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération de la configuration du type de paiement: ' . $e->getMessage());
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
     * Vérifier si un type de paiement est disponible
     *
     * @param int $id
     * @return bool
     */
    public function isPaymentTypeAvailable($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            return $paymentType->is_active;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la vérification de la disponibilité du type de paiement: ' . $e->getMessage());
            return false;
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