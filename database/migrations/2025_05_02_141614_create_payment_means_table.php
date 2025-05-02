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
                $table->id()->primary(); // Clé primaire UUID
                $table->enum('payment_type', ['wave', 'orange_money', 'yass', 'bank_card']); // Type de moyen de paiement
                $table->string('account_identifier'); // Identifiant du compte (chiffré)
                $table->enum('status', ['active', 'inactive']); // Statut du moyen de paiement
                $table->foreignId('user_id')->constrained('users'); // Référence vers l'utilisateur (clé étrangère)
                $table->timestamp('linked_date')->nullable(); // Date de liaison du moyen de paiement
                $table->timestamps(); // Pour created_at et updated_at
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
