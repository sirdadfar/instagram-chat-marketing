<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'is_admin', 'status', 'zernio_api_key_enc'];
    protected $hidden = ['password', 'remember_token', 'zernio_api_key_enc'];
    protected $casts = [
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /** Instagram pages owned by this user. */
    public function instagramAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InstagramAccount::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
