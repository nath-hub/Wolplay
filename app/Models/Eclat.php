<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Eclat extends Model
{
     use HasUuids;

    public $timestamps = false;

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
        'amount'  => 'integer',
        'sent_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /** Quête associée (nullable si don direct hors quête) */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
