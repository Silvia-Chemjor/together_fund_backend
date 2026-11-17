<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'group_id',
        'checkout_request_id',
        'merchant_request_id',
        'phone_number',
        'amount',
        'status',
        'idempotency_key',
        'payment_type'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}