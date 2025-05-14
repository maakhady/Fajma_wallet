<?php

namespace Database\Seeders;

use App\Services\PaymentStatusService;
use Illuminate\Database\Seeder;

class PaymentStatusSeeder extends Seeder
{
    /**
     * Le service de statut de paiement.
     *
     * @var PaymentStatusService
     */
    protected $paymentStatusService;

    /**
     * Constructeur avec injection du service.
     *
     * @param PaymentStatusService $paymentStatusService
     */
    public function __construct(PaymentStatusService $paymentStatusService)
    {
        $this->paymentStatusService = $paymentStatusService;
    }

    /**
     * Seed les statuts de paiement initiaux.
     */
    public function run(): void
    {
        // Définir les statuts à créer
        $statuses = [
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

        // Pour chaque statut, utiliser le service pour créer ou mettre à jour
        foreach ($statuses as $statusData) {
            // Vérifier si le statut existe déjà
            $existingStatus = $this->paymentStatusService->getPaymentStatusByName($statusData['name']);
            
            if ($existingStatus) {
                // Mettre à jour le statut existant
                $this->paymentStatusService->updatePaymentStatus($existingStatus->id, $statusData);
                $this->command->info("Statut mis à jour: " . $statusData['display_name']);
            } else {
                // Créer un nouveau statut
                $this->paymentStatusService->createPaymentStatus($statusData);
                $this->command->info("Statut créé: " . $statusData['display_name']);
            }
        }

        $this->command->info('Tous les statuts de paiement ont été initialisés avec succès.');
    }
}