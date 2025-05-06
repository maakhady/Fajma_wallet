<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'icon',
        'description',
        'is_active',
        'config',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Relation avec les moyens de paiement
     */
    public function paymentMeans()
    {
        return $this->hasMany(PaymentMean::class);
    }
}