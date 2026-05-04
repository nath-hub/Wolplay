<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestContribution extends Model
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
        'eclats_amount'  => 'integer',
        'contributed_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    /** Quête concernée */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /** Fan contributeur (Donneur de quête) */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }
}
