<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasUuids, HasApiTokens;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

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
            'last_login_at'     => 'datetime',
            'pseudo_changed_at' => 'datetime',
            'is_banned'         => 'boolean',
            'password'          => 'hashed',
        ];
    }


    public function sendPasswordResetNotification($token)
    {
        $url = url("/api/reset-password/{$token}?email={$this->email}");

        $this->notify(new ResetPasswordNotification($url));
    }


    // ── Relations ──────────────────────────────────────────────────────────────

    public function socialLinks()
    {
        return $this->hasMany(UserSocialLink::class);
    }

    public function handleHistory()
    {
        return $this->hasMany(HandleHistory::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->latest('started_at');
    }

    // ── Disciplines ────────────────────────────────────────────────────────────

    /** Disciplines du créateur (ordonnées — sort_order ASC) */
    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'user_disciplines')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** Les 3 premières disciplines (affichées sur la CreatorCard) */
    public function featuredDisciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'user_disciplines')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->limit(3);
    }


    // ── Contenu ────────────────────────────────────────────────────────────────

    /** Toutes les vidéos publiées */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'creator_id');
    }

    /** Vidéos visibles (non supprimées, non masquées) */
    public function publishedVideos(): HasMany
    {
        return $this->hasMany(Video::class, 'creator_id')
            ->where('status', 'published');
    }

    /** Vidéos mises en avant (6 slots max) */
    public function featuredVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'featured_videos')
            ->withPivot('slot')
            ->orderByPivot('slot');
    }

    /** Tous les posts (mur de l'Atelier) */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    /** WIP épinglé courant (1 max par créateur) */
    public function pinnedWip(): HasOne
    {
        return $this->hasOne(Post::class, 'author_id')
            ->where('is_pinned', true)
            ->where('is_wip', true);
    }

    /** Collections du créateur */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'owner_id');
    }

    /** Agenda (lives, sorties à venir…) */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class);
    }

    // ── Éclats & Quêtes ────────────────────────────────────────────────────────

    /** Portefeuille d'Éclats */
    public function eclatWallet(): HasOne
    {
        return $this->hasOne(EclatWallet::class);
    }

    /** Éclats envoyés par ce user */
    public function eclatsSent(): HasMany
    {
        return $this->hasMany(Eclat::class, 'sender_id');
    }

    /** Éclats reçus par ce user */
    public function eclatsReceived(): HasMany
    {
        return $this->hasMany(Eclat::class, 'receiver_id');
    }

    /** Quêtes dont ce user est le créateur ciblé */
    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class, 'creator_id');
    }

    /** Quêtes sponsorisées par ce user */
    public function sponsoredQuests(): HasMany
    {
        return $this->hasMany(Quest::class, 'sponsor_id');
    }

    /** Quêtes où ce user a gagné le tirage */
    public function wonQuests(): HasMany
    {
        return $this->hasMany(Quest::class, 'winner_id');
    }

    /** Contributions aux quêtes (fan → Donneurs de quête) */
    public function questContributions(): HasMany
    {
        return $this->hasMany(QuestContribution::class, 'contributor_id');
    }

     // ── Communauté ─────────────────────────────────────────────────────────────

    /** Users que ce user suit */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'follower_id',
            'followed_id'
        )->withPivot('created_at');
    }

    /** Users qui suivent ce user */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'followed_id',
            'follower_id'
        )->withPivot('created_at');
    }

    // ── Modération ─────────────────────────────────────────────────────────────

    /** Signalements déposés par ce user */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /** Actions de modération effectuées par ce user (admin/modo) */
    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class, 'moderator_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * How many days must elapse between pseudo changes (business rule).
     */
    public const PSEUDO_CHANGE_COOLDOWN_DAYS = 30; // premier changement gratuit
    public const PSEUDO_CHANGE_FEE       = 15.00; // €, ensuite sans cooldown

    public function canChangePseudo(): bool
    {
        if (is_null($this->pseudo_changed_at)) {
            return true;
        }

        return $this->pseudo_changed_at
            ->addDays(self::PSEUDO_CHANGE_COOLDOWN_DAYS)
            ->isPast();
    }

    public function nextPseudoChangeAllowedAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->canChangePseudo()) {
            return null;
        }

        return $this->pseudo_changed_at->addDays(self::PSEUDO_CHANGE_COOLDOWN_DAYS);
    }


    /** Le premier changement (≤30j) est-il encore disponible ? */
    public function isFirstPseudoChangeFree(): bool
    {
        return is_null($this->pseudo_changed_at)
            || $this->created_at->addDays(self::PSEUDO_CHANGE_COOLDOWN_DAYS)->isFuture();
    }

    /** Frais à facturer pour le prochain changement de pseudo */
    public function nextPseudoChangeFee(): float
    {
        return $this->isFirstPseudoChangeFree() ? 0.00 : self::PSEUDO_CHANGE_FEE;
    }

    public function isPremium(): bool
    {
        return in_array($this->plan, ['premium', 'pro']);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }
}
