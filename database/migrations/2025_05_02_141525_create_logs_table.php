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
        Schema::create('logs', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // Action réalisée (login, payment, etc.)
            $table->text('description')->nullable(); // Description de l'action
            $table->ipAddress('ip_address')->nullable(); // Adresse IP
            $table->text('user_agent')->nullable(); // Navigateur/appareil utilisé
            $table->string('entity_type')->nullable(); // Type d'entité concernée (morphable)
            $table->unsignedBigInteger('entity_id')->nullable(); // ID de l'entité concernée
            $table->json('metadata')->nullable(); // Données supplémentaires au format JSON
            $table->timestamps(); // created_at et updated_at
            
            // Index pour améliorer les performances des requêtes
            $table->index('action');
            $table->index('created_at');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};