<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Quest extends Model
{

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'eclats_goal'      => 'integer',
        'eclats_collected' => 'integer',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'drawn_at'         => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    /** Créateur ciblé par la quête */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** Sponsor de la quête (user ou null si externe) */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /** Gagnant du tirage */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /** Contributions des fans (Donneurs de quête) */
    public function contributions(): HasMany
    {
        return $this->hasMany(QuestContribution::class);
    }

    /** Flux d'Éclats déclenchés par cette quête */
    public function eclats(): HasMany
    {
        return $this->hasMany(Eclat::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForCreator($query, string $userId)
    {
        return $query->where('creator_id', $userId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function progressPercent(): int
    {
        if ($this->eclats_goal === 0) return 0;
        return (int) min(100, round($this->eclats_collected / $this->eclats_goal * 100));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDormant(): bool
    {
        return $this->status === 'pending';
    }
}
