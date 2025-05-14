<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMean extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nom de la table dans la base de données
     */
    protected $table = 'payment_means';

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_type_id',
        'account_identifier',
        'status',
        'user_id',
        'linked_date',
        'is_default',
        'metadata',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'linked_date' => 'datetime',
        'is_default' => 'boolean',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le type de paiement
     */
    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * Relation avec les transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_mean_id');
    }

    /**
     * Détermine si le moyen de paiement est actif
     */
    public function isActive()
    {
        return $this->status === 'active';
    }
}
