<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        $url = url("/reset-password/{$token}?email={$this->email}");

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

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * How many days must elapse between pseudo changes (business rule).
     */
    public const PSEUDO_CHANGE_COOLDOWN_DAYS = 30;

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
}
