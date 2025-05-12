<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            // Prestataires de santé
            [
                'structure_name' => 'Hôpital Principal de Dakar',
                'address' => '1, Avenue Nelson Mandela, Dakar',
                'phone' => '338395050',
                'email' => 'contact@hopitalprincipal.sn',
                'provider_type' => 'prestataire_sante',
                'status' => 'active',
                'description' => 'Hôpital de référence à Dakar',
                'commission_rate' => 2.5,
            ],
            [
                'structure_name' => 'Clinique du Cap',
                'address' => 'Almadies, Dakar',
                'phone' => '338202020',
                'email' => 'info@cliniquecap.sn',
                'provider_type' => 'prestataire_sante',
                'status' => 'active',
                'description' => 'Clinique privée spécialisée',
                'commission_rate' => 3.0,
            ],
            [
                'structure_name' => 'Centre de Santé Philippe Senghor',
                'address' => 'Yoff, Dakar',
                'phone' => '338250950',
                'email' => 'centre@senghor.sn',
                'provider_type' => 'prestataire_sante',
                'status' => 'active',
                'description' => 'Centre de santé communautaire',
                'commission_rate' => 1.5,
            ],
            [
                'structure_name' => 'Pharmacie des Parcelles',
                'address' => 'Parcelles Assainies, Dakar',
                'phone' => '338350505',
                'email' => 'pharma@parcelles.sn',
                'provider_type' => 'prestataire_sante',
                'status' => 'pending',
                'description' => 'Pharmacie de proximité',
                'commission_rate' => 2.0,
            ],

            // Services financiers
            [
                'structure_name' => 'Wave Sénégal',
                'address' => 'Dakar Plateau, Dakar',
                'phone' => '771234567',
                'email' => 'support@wave.com',
                'provider_type' => 'service_finance',
                'status' => 'active',
                'description' => 'Service de transfert d\'argent mobile',
                'commission_rate' => 1.0,
            ],
            [
                'structure_name' => 'Orange Money',
                'address' => 'Avenue Cheikh Anta Diop, Dakar',
                'phone' => '780001122',
                'email' => 'om@orange.sn',
                'provider_type' => 'service_finance',
                'status' => 'active',
                'description' => 'Service financier de l\'opérateur Orange',
                'commission_rate' => 1.2,
            ],
            [
                'structure_name' => 'Wari',
                'address' => 'VDN, Dakar',
                'phone' => '338210000',
                'email' => 'contact@wari.sn',
                'provider_type' => 'service_finance',
                'status' => 'inactive',
                'description' => 'Service de transfert d\'argent',
                'commission_rate' => 0.8,
            ],
        ];

        foreach ($providers as $providerData) {
            Provider::updateOrCreate(
                ['structure_name' => $providerData['structure_name']],
                $providerData
            );
        }

        $this->command->info('Prestataires créés avec succès');
    }
}
