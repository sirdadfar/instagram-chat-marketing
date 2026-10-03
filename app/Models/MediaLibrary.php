<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaLibrary extends Model
{
    protected $table = 'media_library';

    protected $fillable = ['name', 'type', 'url', 'mime_type', 'size', 'alt_text', 'metadata', 'active'];

    protected $casts = [
        'metadata' => 'array',
        'active' => 'boolean',
    ];
}
