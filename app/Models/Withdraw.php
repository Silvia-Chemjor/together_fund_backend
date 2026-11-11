<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    protected $table = 'withdraws';

    protected $fillable = [
        'treasurer_id',
        'phone_number',
        'amount',
        'reason',
        'status',
        'idempotency_key',
        'conversation_id',
        'originator_conversation_id',
        'transaction_id',
        'result_code',
        'result_description',
    ];
}