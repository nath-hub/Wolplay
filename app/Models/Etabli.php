<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Etabli extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'etablis';

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

    protected function casts(): array
    {
        return [
            'images'            => 'array',
            'image_expirations' => 'array',
            'is_pinned'         => 'boolean',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    /**
     * Créateur de l'établi
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOfCreator($query, $creatorId)
    {
        return $query->where('creator_id', $creatorId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position', 'asc');
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Nombre d'images
     */
    public function getImageCountAttribute(): int
    {
        return count($this->images ?? []);
    }
}
