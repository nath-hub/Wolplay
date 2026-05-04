<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EclatWallet extends Model
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

     protected $casts = ['balance' => 'integer'];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function credit(int $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function debit(int $amount): void
    {
        if ($this->balance < $amount) {
            throw new \RuntimeException('Solde d\'Éclats insuffisant.');
        }
        $this->decrement('balance', $amount);
    }

    public function hasEnough(int $amount): bool
    {
        return $this->balance >= $amount;
    }
}
