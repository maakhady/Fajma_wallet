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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); // Identifiant autoincrémenté
            $table->foreignId('user_id')->constrained(); // Utilisateur qui a effectué la transaction
            $table->foreignId('card_id')->constrained(); // Référence à la carte liée à la transaction
            $table->decimal('amount', 10, 2); // Montant de la transaction avec précision décimale
            $table->timestamp('transaction_date'); // Date de la transaction
            $table->foreignId('transaction_type_id')->constrained(); // Type de la transaction
            $table->foreignId('provider_id')->nullable()->constrained(); // Référence au prestataire de santé (peut être null)
            $table->foreignId('payment_mean_id')->nullable()->constrained(); // Référence au moyen de paiement (peut être null)
            $table->foreignId('payment_status_id')->constrained('payment_status'); // Référence au statut de la transaction
            $table->decimal('previous_balance', 10, 2); // Solde avant la transaction (renommé pour cohérence)
            $table->decimal('current_balance', 10, 2); // Solde après la transaction (renommé pour cohérence)
            $table->string('transaction_uid')->unique(); // Identifiant unique de la transaction
            $table->text('description')->nullable(); // Description de la transaction
            $table->json('metadata')->nullable(); // Données supplémentaires au format JSON
            $table->timestamps(); // Création des colonnes created_at et updated_at
            $table->softDeletes(); // Ajout du soft delete

            // Index pour améliorer les performances
            $table->index('transaction_date');
            $table->index(['user_id', 'transaction_date']);
            $table->index(['card_id', 'transaction_date']);
            $table->index('payment_status_id');
            $table->index('deleted_at'); // Index pour les soft deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
