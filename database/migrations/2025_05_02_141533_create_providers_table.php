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
            $table->string('phone')->nullable(); // Numéro de téléphone (nullable)
            $table->string('email')->nullable(); // Email (nullable)
            $table->enum('provider_type', ['prestataire_sante', 'service_finance']); // Type de prestataire
            $table->timestamps(); // pour created_at et updated_at
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
