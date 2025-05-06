<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
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
        'description',
        'icon',
        'color',
        'is_credit',
        'is_active',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_credit' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * La méthode qui définit la relation avec les transactions.
     * Un type de transaction peut être associé à plusieurs transactions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    
    /**
     * Détermine si le type de transaction est un crédit.
     *
     * @return bool
     */
    public function isCredit()
    {
        return $this->is_credit;
    }
    
    /**
     * Détermine si le type de transaction est un débit.
     *
     * @return bool
     */
    public function isDebit()
    {
        return !$this->is_credit;
    }
}