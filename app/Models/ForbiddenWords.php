<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ForbiddenWords extends Model
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


       /** Vérifie si un texte contient un mot interdit et retourne l'action à prendre. */
    public static function check(string $text): ?self
    {
        $words = self::all();

        foreach ($words as $entry) {
            if (stripos($text, $entry->word) !== false) {
                return $entry;
            }
        }

        return null;
    }
}
