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
            $table->foreignId('card_id')->constrained('cards'); // Référence à la carte liée à la transaction
            $table->float('amount'); // Montant de la transaction
            $table->timestamp('transaction_date'); // Date de la transaction
            $table->foreignId('transaction_type_id')->constrained('transaction_types'); // Type de la transaction
            $table->foreignId('provider_id')->constrained('providers'); // Référence au prestataire de santé
            $table->foreignId('payment_mean_id')->constrained('payment_means'); // Référence au moyen de paiement
            $table->foreignId('payment_status_id')->constrained('payment_status'); // Référence au statut de la transaction
            $table->float('previous_solde'); // Solde avant la transaction
            $table->float('current_solde'); // Solde après la transaction
            $table->string('transaction_uid')->unique(); // Identifiant unique de la transaction
            $table->timestamps(); // Création des colonnes created_at et updated_at
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
