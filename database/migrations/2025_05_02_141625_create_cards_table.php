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
        Schema::create('cards', function (Blueprint $table) {
            $table->id(); // clé primaire auto-incrémentée
            $table->string('card_number')->unique(); // Numéro de la carte, unique
            $table->enum('type_card', ['physical', 'virtual']); // Type de carte (physique ou virtuelle)
            $table->enum('status', ['activated', 'deactivated', 'blocked']); // Statuts élargis
            $table->decimal('balance', 10, 2)->default(0.00); // Solde avec précision décimale (renommé de "solde")
            $table->date('expires_at')->nullable(); // Date d'expiration de la carte
            $table->foreignId('user_id')->constrained(); // Convention Laravel standard (renommé de "users_id")
            $table->timestamps(); // pour created_at et updated_at
            
            // Index pour améliorer les performances
            $table->index('status');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};