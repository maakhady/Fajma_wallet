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
        Schema::create('payment_status', function (Blueprint $table) {
            $table->id(); // clé primaire auto-incrémentée
            $table->string('name')->unique(); // Nom du statut (code)
            $table->string('display_name'); // Nom d'affichage pour l'interface
            $table->text('description')->nullable(); // Description du statut
            $table->string('color')->nullable(); // Couleur pour l'interface (optionnel)
            $table->timestamps(); // pour created_at et updated_at
            $table->softDeletes(); // Ajout du soft delete


            // Ajout d'index pour améliorer les performances des requêtes fréquentes
            $table->index('deleted_at'); // Index pour les soft deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_status');
    }
};
