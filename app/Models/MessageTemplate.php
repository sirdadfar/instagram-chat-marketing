<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = ['name', 'slug', 'body', 'language', 'category', 'variables', 'active'];

    protected $casts = [
        'variables' => 'array',
        'active' => 'boolean',
    ];
}
