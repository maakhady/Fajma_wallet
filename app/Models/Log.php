<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'entity_type',
        'entity_id',
        'metadata',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * La relation avec l'utilisateur associé au log.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation polymorphique vers l'entité concernée (si applicable).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function entity()
    {
        return $this->morphTo();
    }
}