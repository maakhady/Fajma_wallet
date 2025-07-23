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
        Schema::create('payment_means', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_type_id')->constrained(); // Relation avec le type de paiement
            $table->string('account_identifier'); // Identifiant du compte (chiffré)
            $table->enum('status', ['active', 'inactive']); // Statut du moyen de paiement
            $table->foreignId('user_id')->constrained(); // Référence vers l'utilisateur
            $table->timestamp('linked_date')->nullable(); // Date de liaison du moyen de paiement
            $table->boolean('is_default')->default(false); // Moyen de paiement par défaut
            $table->json('metadata')->nullable(); // Données supplémentaires
            $table->timestamps();
            $table->softDeletes(); // Ajout du soft delete


            // Index pour optimiser les performances
            $table->index(['user_id', 'status']);
            $table->index('payment_type_id');
            $table->index('deleted_at'); // Index pour les soft deletes

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_means');
    }
};
