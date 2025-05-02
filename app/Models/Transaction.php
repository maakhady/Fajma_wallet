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
        'previous_solde',
        'current_solde',
        'transaction_uid',
    ];

    /**
     * Les relations avec d'autres modèles.
     */

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
}
