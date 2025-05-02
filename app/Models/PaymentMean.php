<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMean extends Model
{
    use HasFactory;

    // Nom de la table dans la base de données
    protected $table = 'payment_means';

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_type',
        'account_identifier',
        'status',
        'user_id',
    ];

    /**
     * La méthode qui définit la relation avec l'utilisateur.
     * Un moyen de paiement appartient à un utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * La méthode qui définit la relation avec les transactions.
     * Un moyen de paiement peut avoir plusieurs transactions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
