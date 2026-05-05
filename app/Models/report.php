<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class report extends Model
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


    protected $casts = [
        'created_at' => 'datetime',
    ];

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

    /** User qui a déposé le signalement */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Cible polymorphique : résout dynamiquement vers Video ou Post.
     * Utilisation : $report->target
     */
    public function target(): Video|Post|null
    {
        return match ($this->target_type) {
            'video' => Video::find($this->target_id),
            'post'  => Post::find($this->target_id),
            default => null,
        };
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForVideos($query)
    {
        return $query->where('target_type', 'video');
    }

    public function scopeForPosts($query)
    {
        return $query->where('target_type', 'post');
    }
}
