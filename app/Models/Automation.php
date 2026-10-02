<?php
namespace App\Models;
use App\Enums\AutomationTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Automation extends Model {
 protected $fillable=['instagram_account_id','name','description','trigger','status','match_mode','target_type','target_id','cooldown_seconds','max_per_user_day','settings','audience','execution_mode','priority','global_enabled','business_hours_only','handoff_enabled','sync_to_zernio','zernio_automation_id'];
 protected $casts=['trigger'=>AutomationTrigger::class,'settings'=>'array','audience'=>'array','global_enabled'=>'boolean','business_hours_only'=>'boolean','handoff_enabled'=>'boolean'];
 public function account(): BelongsTo{return $this->belongsTo(InstagramAccount::class,'instagram_account_id');}
 public function keywords(): HasMany{return $this->hasMany(AutomationKeyword::class);}
 public function actions(): HasMany{return $this->hasMany(AutomationActionModel::class,'automation_id')->orderBy('sort_order');}
 public function logs(): HasMany{return $this->hasMany(AutomationLog::class);} public function versions(): HasMany{return $this->hasMany(AutomationVersion::class);} public function nodes(): HasMany{return $this->hasMany(AutomationNode::class);} public function edges(): HasMany{return $this->hasMany(AutomationEdge::class);} public function runs(): HasMany{return $this->hasMany(AutomationRun::class);}
}
