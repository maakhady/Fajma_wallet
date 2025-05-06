<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'card_id',
        'amount',
        'transaction_date',
        'transaction_type_id',
        'provider_id',
        'payment_mean_id',
        'payment_status_id',
        'previous_balance',
        'current_balance',
        'transaction_uid',
        'description',
        'metadata',
        'user_id',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'transaction_date' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Les relations avec d'autres modèles.
     */
    
    // Relation avec le modèle User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation avec le modèle Card
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    // Relation avec le modèle TransactionType
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    // Relation avec le modèle Provider
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    // Relation avec le modèle PaymentMean
    public function paymentMean()
    {
        return $this->belongsTo(PaymentMean::class);
    }

    // Relation avec le modèle PaymentStatus
    public function paymentStatus()
    {
        return $this->belongsTo(PaymentStatus::class);
    }
    
    /**
     * Détermine si la transaction est un crédit (ajout d'argent)
     */
    public function isCredit()
    {
        return $this->transactionType->is_credit;
    }
    
    /**
     * Détermine si la transaction est un débit (retrait d'argent)
     */
    public function isDebit()
    {
        return !$this->transactionType->is_credit;
    }
    
    /**
     * Détermine si la transaction est complétée avec succès
     */
    public function isCompleted()
    {
        return $this->paymentStatus->name === 'paid';
    }
}