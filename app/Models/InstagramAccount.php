<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class InstagramAccount extends Model { protected $fillable=['user_id','zernio_account_id','username','name','status','metadata']; protected $casts=['metadata'=>'array']; public function automations():HasMany{return $this->hasMany(Automation::class);} public function user():\Illuminate\Database\Eloquent\Relations\BelongsTo{return $this->belongsTo(User::class);} }
