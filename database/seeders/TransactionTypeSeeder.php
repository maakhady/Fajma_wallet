<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Seed les types de transactions initiaux.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'deposit',
                'display_name' => 'Dépôt',
                'description' => 'Ajout de fonds à la carte',
                'icon' => 'arrow-down-circle',
                'color' => '#4CAF50', // Vert
                'is_credit' => true,
                'is_active' => true,
            ],
            [
                'name' => 'withdrawal',
                'display_name' => 'Retrait',
                'description' => 'Retrait de fonds de la carte',
                'icon' => 'arrow-up-circle',
                'color' => '#F44336', // Rouge
                'is_credit' => false,
                'is_active' => true,
            ],
            [
                'name' => 'payment',
                'display_name' => 'Paiement',
                'description' => 'Paiement à un prestataire de santé',
                'icon' => 'credit-card',
                'color' => '#2196F3', // Bleu
                'is_credit' => false,
                'is_active' => true,
            ],
            [
                'name' => 'refund',
                'display_name' => 'Remboursement',
                'description' => 'Remboursement d\'un paiement',
                'icon' => 'refresh-cw',
                'color' => '#9C27B0', // Violet
                'is_credit' => true,
                'is_active' => true,
            ],
            [
                'name' => 'transfer',
                'display_name' => 'Transfert',
                'description' => 'Transfert entre utilisateurs',
                'icon' => 'users',
                'color' => '#FF9800', // Orange
                'is_credit' => false, // Du point de vue de l'expéditeur
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            TransactionType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}