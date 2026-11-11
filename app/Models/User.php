<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'user_image',
        'is_active',
        'role_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // User role relationship
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // User permissions
    public function abilities()
    {
        return [
            'admin' => $this->role?->name === 'ADMINISTRATOR',
            'user' => $this->role?->name === 'USER',
            'treasurer' => $this->role?->name === 'TREASURER'
        ];
    }
}
