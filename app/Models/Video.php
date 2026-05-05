<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
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


    protected $casts = [
        'author_certified' => 'boolean',
        'is_featured'      => 'boolean',
        'is_wolplay_pick'  => 'boolean',
        'published_at'     => 'datetime',
    ];



    // ── Relations ──────────────────────────────────────────────────────────────

    /** Créateur de la vidéo */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** Catégorie (Wolplays / Tutorials / Collections) */
    public function category()
    {
        return $this->belongsToMany(Categorie::class, 'video_categories');
    }

    public function formats()
    {
        return $this->belongsToMany(Format::class, 'video_formats');
    }

    /** Disciplines associées (0–2 selon catégorie) */
    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'video_disciplines');
    }

    /** Tags libres */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'video_tags');
    }

    /** Créateurs qui ont cette vidéo dans leurs slots mis en avant */
    public function featuredBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'featured_videos')
            ->withPivot('slot')
            ->orderByPivot('slot');
    }

    /** Collections contenant cette vidéo */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_videos')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** Post de type "video" associé à cette vidéo */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** Signalements sur cette vidéo */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'target_id')
            ->where('target_type', 'video');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeWolplayPick($query)
    {
        return $query->where('is_wolplay_pick', true);
    }

    public function scopeByCategory($query, ?string $categoryName = null)
    {
        if (!$categoryName) {
            return $query; // 👉 pas de filtre si vide
        }

        return $query->whereHas('category', function ($q) use ($categoryName) {
            $q->where('name', $categoryName);
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isYoutube(): bool
    {
        return $this->platform === 'youtube';
    }

    public function isTwitch(): bool
    {
        return $this->platform === 'twitch';
    }

    public function isBroken(): bool
    {
        return $this->status === 'broken';
    }
}
