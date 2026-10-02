<?php
namespace App\Services\Automation;
use App\Models\RateLimitBucket;
use Illuminate\Support\Facades\DB;
class RateLimiter { public function allow(string $key,int $max,int $windowSeconds=60):bool { $now=now(); $row=RateLimitBucket::firstOrCreate(['bucket_key'=>$key],['hits'=>0,'window_started_at'=>$now]); if($row->window_started_at->lt($now->copy()->subSeconds($windowSeconds))){$row->update(['hits'=>0,'window_started_at'=>$now]);$row->refresh();} if($row->hits >= $max)return false; $row->increment('hits'); return true; } }
