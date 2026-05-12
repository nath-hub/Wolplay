<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
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

    protected function casts(): array
    {
        return [
            'is_pinned'      => 'boolean',
            'is_wip'         => 'boolean',
            'wip_progress'   => 'integer',
            'images'         => 'array',
            'source_snapshot' => 'array',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    /** Auteur du post */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Signalements sur ce post */
    public function reports(): HasMany
    {
        return $this->hasMany('App\Models\report', 'target_id')
            ->where('target_type', 'post');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeWip($query)
    {
        return $query->where('is_wip', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('post_type', $type);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Nombre d'images
     */
    public function getImageCountAttribute(): int
    {
        return count($this->images ?? []);
    }

    public function isVideo(): bool
    {
        return $this->post_type === 'video';
    }

    public function isWip(): bool
    {
        return $this->post_type === 'wip';
    }

    public function isPhoto(): bool
    {
        return $this->post_type === 'photo';
    }

    public function isText(): bool
    {
        return $this->post_type === 'text';
    }

    public function isSharedPost(): bool
    {
        return $this->type === 'shared_post';
    }

    public function isSharedEtabli(): bool
    {
        return $this->type === 'shared_etabli';
    }
}
