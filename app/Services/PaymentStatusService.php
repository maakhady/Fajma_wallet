<?php

namespace App\Services;

use App\Models\PaymentStatus;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;

class PaymentStatusService
{
    /**
     * Récupérer tous les statuts de paiement
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPaymentStatuses()
    {
        try {
            return PaymentStatus::all();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des statuts de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un statut de paiement par son ID
     *
     * @param int $id
     * @return PaymentStatus
     */
    public function getPaymentStatusById($id)
    {
        try {
            return PaymentStatus::findOrFail($id);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du statut de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un statut de paiement par son nom
     *
     * @param string $name
     * @return PaymentStatus|null
     */
    public function getPaymentStatusByName($name)
    {
        try {
            return PaymentStatus::where('name', $name)->first();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération du statut de paiement par nom: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Créer un nouveau statut de paiement
     *
     * @param array $data
     * @return PaymentStatus
     */
    public function createPaymentStatus(array $data)
    {
        try {
            // Créer le statut de paiement
            $paymentStatus = PaymentStatus::create($data);

            // Journaliser la création
            $this->logAction('create_payment_status', $paymentStatus->id, 'Création du statut de paiement: ' . $paymentStatus->display_name);

            return $paymentStatus;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la création du statut de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un statut de paiement existant
     *
     * @param int $id
     * @param array $data
     * @return PaymentStatus
     */
    public function updatePaymentStatus($id, array $data)
    {
        try {
            $paymentStatus = $this->getPaymentStatusById($id);

            // Mettre à jour le statut de paiement
            $paymentStatus->update($data);

            // Journaliser la mise à jour
            $this->logAction('update_payment_status', $paymentStatus->id, 'Mise à jour du statut de paiement: ' . $paymentStatus->display_name);

            return $paymentStatus;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour du statut de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer un statut de paiement
     *
     * @param int $id
     * @return bool
     */
    public function deletePaymentStatus($id)
    {
        try {
            $paymentStatus = $this->getPaymentStatusById($id);

            // Vérifier si le statut est utilisé par des transactions
            if ($paymentStatus->transactions()->count() > 0) {
                throw new \Exception('Ce statut de paiement est utilisé par des transactions et ne peut pas être supprimé.');
            }

            // Journaliser la suppression avant de supprimer
            $this->logAction('delete_payment_status', $paymentStatus->id, 'Suppression du statut de paiement: ' . $paymentStatus->display_name);

            // Supprimer le statut
            return $paymentStatus->delete();
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la suppression du statut de paiement: ' . $e->getMessage());
            throw $e;
        }
    }

/**
 * Initialiser les statuts de paiement par défaut
 *
 * @return array
 */
public function initializeDefaultStatuses()
{
    try {
        $defaultStatuses = [
            [
                'name' => 'pending',
                'display_name' => 'En attente',
                'description' => 'La transaction est en cours de traitement',
                'color' => '#FFC107', // Jaune
            ],
            [
                'name' => 'paid',
                'display_name' => 'Payé',
                'description' => 'La transaction a été complétée avec succès',
                'color' => '#4CAF50', // Vert
            ],
            [
                'name' => 'failed',
                'display_name' => 'Échoué',
                'description' => 'La transaction a échoué',
                'color' => '#F44336', // Rouge
            ],
            [
                'name' => 'cancelled',
                'display_name' => 'Annulé',
                'description' => 'La transaction a été annulée',
                'color' => '#9E9E9E', // Gris
            ],
            [
                'name' => 'refunded',
                'display_name' => 'Remboursé',
                'description' => 'Le montant a été remboursé',
                'color' => '#2196F3', // Bleu
            ],
        ];

        $createdStatuses = [];

        foreach ($defaultStatuses as $statusData) {
            // Vérifier si le statut existe déjà
            $existing = $this->getPaymentStatusByName($statusData['name']);
            
            if (!$existing) {
                // Créer le statut s'il n'existe pas
                $createdStatuses[] = $this->createPaymentStatus($statusData);
            } else {
                // Mettre à jour le statut existant
                $this->updatePaymentStatus($existing->id, $statusData);
            }
        }

        return $createdStatuses;
    } catch (\Exception $e) {
        LogFacade::error('Erreur lors de l\'initialisation des statuts de paiement par défaut: ' . $e->getMessage());
        throw $e;
    }
}

    /**
     * Récupérer le nombre de transactions par statut
     *
     * @return array
     */
    public function getTransactionsCountByStatus()
    {
        try {
            $result = [];
            $statuses = $this->getAllPaymentStatuses();

            foreach ($statuses as $status) {
                $result[] = [
                    'status_id' => $status->id,
                    'status_name' => $status->name,
                    'display_name' => $status->display_name,
                    'color' => $status->color,
                    'count' => $status->transactions()->count()
                ];
            }

            return $result;
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors du comptage des transactions par statut: ' . $e->getMessage());
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
                'entity_type' => 'payment_status',
                'entity_id' => $entityId,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la journalisation: ' . $e->getMessage());
        }
    }
}