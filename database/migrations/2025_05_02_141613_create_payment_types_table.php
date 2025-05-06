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
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // ex: wave, orange_money, yass, etc.
            $table->string('display_name'); // Nom à afficher à l'utilisateur
            $table->string('icon')->nullable(); // Chemin vers l'icône
            $table->text('description')->nullable(); // Description du type de paiement
            $table->boolean('is_active')->default(true); // Type de paiement actif ou non
            $table->json('config')->nullable(); // Configuration spécifique au type de paiement
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_types');
    }
};