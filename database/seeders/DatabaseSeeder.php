<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeders de base pour les types et statuts
        $this->call([
            PaymentTypeSeeder::class,
            PaymentStatusSeeder::class,
            TransactionTypeSeeder::class,
            ProviderSeeder::class,
        ]);

        // Vous pourrez ajouter d'autres seeders ultérieurement
    }
}
