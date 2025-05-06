<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'profile_photo',
        'password',
        'verification_code',
        'role',
        'is_active',  // Ajouté pour correspondre à la migration
        'last_login_at',  // Ajouté pour correspondre à la migration
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',  // Ajouté pour le nouveau champ
            'is_active' => 'boolean',      // Ajouté pour le nouveau champ
            'password' => 'hashed',
        ];
    }

    /**
     * Récupère l'identifiant JWT de l'utilisateur.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Utilise la clé primaire (ID) de l'utilisateur
    }

    /**
     * Récupère les revendications personnalisées pour le token JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        return []; // Tu peux ajouter des revendications personnalisées ici, si besoin
    }

    /**
     * Accesseur pour obtenir le nom complet
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Met à jour la date de dernière connexion
     */
    public function updateLastLogin(): void
    {
        $this->last_login_at = now();
        $this->save();
    }

    // Relations
    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }
    
     /**
     * Relation avec les moyens de paiement
     */
    public function paymentMeans()
    {
        return $this->hasMany(PaymentMean::class);
    }
}