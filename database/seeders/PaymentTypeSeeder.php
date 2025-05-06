<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Seed les types de paiement initiaux.
     */
    public function run(): void
    {
        $paymentTypes = [
            [
                'name' => 'wave',
                'display_name' => 'Wave',
                'description' => 'Paiement via Wave Money Transfer',
                'icon' => 'images/payment-types/wave.png',
            ],
            [
                'name' => 'orange_money',
                'display_name' => 'Orange Money',
                'description' => 'Paiement via Orange Money',
                'icon' => 'images/payment-types/orange-money.png',
            ],
            [
                'name' => 'yass',
                'display_name' => 'Yass',
                'description' => 'Paiement via Yass',
                'icon' => 'images/payment-types/yass.png',
            ],
            [
                'name' => 'bank_card',
                'display_name' => 'Carte Bancaire',
                'description' => 'Paiement par carte bancaire',
                'icon' => 'images/payment-types/bank-card.png',
            ],
        ];

        foreach ($paymentTypes as $type) {
            PaymentType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}