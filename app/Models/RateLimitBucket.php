<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RateLimitBucket extends Model { protected $fillable=['bucket_key','hits','window_started_at']; protected $casts=['window_started_at'=>'datetime']; }
