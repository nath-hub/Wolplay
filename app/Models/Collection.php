<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasUuids;
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

     // ── Relations ──────────────────────────────────────────────────────────────

    /** Propriétaire de la collection */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Vidéos dans la collection (ordonnées) */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'collection_videos')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePublic($query)
    {
        return $query->where('status', 'public');
    }
}
