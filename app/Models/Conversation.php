<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Conversation extends Model { protected $fillable=['instagram_account_id','contact_id','zernio_conversation_id','status','needs_human','last_message_at','last_inbound_at','unread_count','metadata']; protected $casts=['needs_human'=>'boolean','last_message_at'=>'datetime','last_inbound_at'=>'datetime','metadata'=>'array']; public function messages():HasMany{return $this->hasMany(Message::class);} public function contact(){return $this->belongsTo(Contact::class);} public function instagramAccount(){return $this->belongsTo(InstagramAccount::class,'instagram_account_id');} }
