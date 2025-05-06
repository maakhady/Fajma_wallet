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
        'status',
        'logo',
        'description',
        'commission_rate',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'commission_rate' => 'decimal:2',
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
    
    /**
     * La méthode qui définit la relation avec l'utilisateur admin associé.
     * Un provider peut être associé à un utilisateur admin.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Détermine si le prestataire est actif
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }
}