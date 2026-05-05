<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Discipline extends Model
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
    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    /** Créateurs ayant cette discipline */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_disciplines')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** Vidéos taggées avec cette discipline */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_disciplines');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
