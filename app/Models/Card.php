<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'card_number',
        'type_card',
        'status',
        'balance',
        'expires_at',
        'user_id',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:2',
        'expires_at' => 'date',
    ];

    /**
     * La méthode qui définit la relation avec l'utilisateur.
     * Une carte appartient à un utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La méthode qui définit la relation avec les transactions.
     * Une carte peut avoir plusieurs transactions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Détermine si la carte est active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'activated';
    }

    /**
     * Détermine si la carte est expirée.
     *
     * @return bool
     */
    public function isExpired()
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
