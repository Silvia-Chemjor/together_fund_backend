<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Group extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'created_by',
    ];

    // Treasurer who created the group
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Group members
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}
