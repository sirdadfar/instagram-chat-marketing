<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Contact extends Model { protected $fillable=['instagram_account_id','external_user_id','username','full_name','is_follower','follower_count','is_verified','state','needs_human','last_seen_at','metadata']; protected $casts=['is_follower'=>'boolean','is_verified'=>'boolean','needs_human'=>'boolean','last_seen_at'=>'datetime','metadata'=>'array']; public function tags():BelongsToMany{return $this->belongsToMany(Tag::class);} }
