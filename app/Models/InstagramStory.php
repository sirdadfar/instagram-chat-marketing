<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InstagramStory extends Model {
 protected $fillable=['instagram_account_id','zernio_story_id','permalink','media_type','media_url','thumbnail_url','published_at','expires_at','metadata'];
 protected $casts=['metadata'=>'array','published_at'=>'datetime','expires_at'=>'datetime'];
 public function account(): BelongsTo{return $this->belongsTo(InstagramAccount::class,'instagram_account_id');}
}
