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
            $table->enum('status', ['activated', 'deactivated']); // Statut de la carte (activée ou désactivée)
            $table->float('solde')->default(0.0); // Solde de la carte
            $table->foreignId('users_id')->constrained('users'); // Référence vers l'utilisateur (clé étrangère)
            $table->timestamps(); // pour created_at et updated_at
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
