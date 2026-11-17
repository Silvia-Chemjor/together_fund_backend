<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'role',
        'action',
        'type',
        'status',
    ];
}
