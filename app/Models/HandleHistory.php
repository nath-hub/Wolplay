<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandleHistory extends Model
{

    protected $table = 'handle_history';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id'];

    public $timestamps = false;
}
