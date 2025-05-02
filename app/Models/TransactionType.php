<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    use HasFactory;

    // Nom de la table dans la base de données (si c'est différent du nom de la classe)
    protected $table = 'transaction_types';

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_type_label',
    ];

    /**
     * La méthode qui définit la relation avec les transactions.
     * Une transaction peut avoir un type de transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
