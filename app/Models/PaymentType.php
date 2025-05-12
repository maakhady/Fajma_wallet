<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Ajoutez cette ligne


class PaymentType extends Model
{
    use HasFactory, SoftDeletes; // Ajoutez cette ligne

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
        'deleted_at' => 'datetime', // Ajoutez cette ligne

    ];

    /**
     * Relation avec les moyens de paiement
     */
    public function paymentMeans()
    {
        return $this->hasMany(PaymentMean::class);
    }
}
