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
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Code unique (deposit, withdrawal, etc.)
            $table->string('display_name'); // Nom d'affichage (Dépôt, Retrait, etc.)
            $table->text('description')->nullable(); // Description du type de transaction
            $table->string('icon')->nullable(); // Icône associée au type de transaction
            $table->string('color')->nullable(); // Couleur associée au type de transaction
            $table->boolean('is_credit')->default(false); // True si c'est un crédit, false si c'est un débit
            $table->boolean('is_active')->default(true); // Indique si ce type est actif
            $table->timestamps();
            $table->softDeletes(); // Ajout du soft delete
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_types');
    }
};
