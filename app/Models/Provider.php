<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use HasFactory;

    // Nom de la table dans la base de données
    protected $table = 'providers';

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'structure_name',
        'address',
        'phone',
        'email',
        'provider_type',
    ];

    /**
     * La méthode qui définit la relation avec les transactions.
     * Un provider peut avoir plusieurs transactions de santé.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
