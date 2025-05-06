<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id(); // clé primaire auto-incrémentée
            $table->string('structure_name'); // Nom de la structure
            $table->string('address'); // Adresse du prestataire
            $table->string('phone', 20)->nullable(); // Numéro de téléphone (nullable)
            $table->string('email')->nullable(); // Email (nullable)
            $table->enum('provider_type', ['prestataire_sante', 'service_finance']); // Type de prestataire
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending'); // Statut du prestataire
            $table->string('logo')->nullable(); // Logo du prestataire
            $table->text('description')->nullable(); // Description du prestataire
            $table->decimal('commission_rate', 5, 2)->default(0.00); // Taux de commission en pourcentage
            $table->foreignId('user_id')->nullable()->constrained(); // Lien vers l'utilisateur admin
            $table->timestamps(); // pour created_at et updated_at
            
            // Index pour améliorer les performances
            $table->index('provider_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};