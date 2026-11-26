<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookEvent extends Model { protected $fillable=['provider','event_id','event_type','instagram_account_id','payload','status','processed_at','error']; protected $casts=['payload'=>'array','processed_at'=>'datetime']; public function instagramAccount(){return $this->belongsTo(InstagramAccount::class);} }
