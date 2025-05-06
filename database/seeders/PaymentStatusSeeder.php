<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use Illuminate\Database\Seeder;

class PaymentStatusSeeder extends Seeder
{
    /**
     * Seed les statuts de paiement initiaux.
     */
    public function run(): void
    {
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

        foreach ($statuses as $status) {
            PaymentStatus::updateOrCreate(
                ['name' => $status['name']],
                $status
            );
        }
    }
}