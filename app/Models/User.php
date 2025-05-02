<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;  // <-- Ajouter cette ligne

class User extends Authenticatable implements JWTSubject  // <-- Ajouter l'implémentation de l'interface JWTSubject
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
            'password' => 'hashed',
        ];
    }

    // Implémentation des méthodes requises par l'interface JWTSubject
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

    // Relations (inchangées)
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
}
