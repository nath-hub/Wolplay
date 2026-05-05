<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Categorie extends Model
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
        'min_disciplines' => 'integer',
        'max_disciplines' => 'integer',
        'format_required' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function requiresDiscipline(): bool
    {
        return $this->min_disciplines > 0;
    }

    public function acceptsDisciplines(): bool
    {
        return $this->max_disciplines > 0;
    }
}
