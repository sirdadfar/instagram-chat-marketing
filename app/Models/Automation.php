<?php
namespace App\Models;
use App\Enums\AutomationTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Automation extends Model {
 protected $fillable=['instagram_account_id','name','description','trigger','triggers','status','match_mode','target_type','target_id','cooldown_seconds','max_per_user_day','settings','audience','execution_mode','priority','global_enabled','business_hours_only','handoff_enabled','sync_to_zernio','zernio_automation_id'];
 protected $casts=['trigger'=>AutomationTrigger::class,'triggers'=>'array','settings'=>'array','audience'=>'array','global_enabled'=>'boolean','business_hours_only'=>'boolean','handoff_enabled'=>'boolean'];
 public function account(): BelongsTo{return $this->belongsTo(InstagramAccount::class,'instagram_account_id');}
 public function keywords(): HasMany{return $this->hasMany(AutomationKeyword::class);}
 public function actions(): HasMany{return $this->hasMany(AutomationActionModel::class,'automation_id')->orderBy('sort_order');}
 public function logs(): HasMany{return $this->hasMany(AutomationLog::class);} public function versions(): HasMany{return $this->hasMany(AutomationVersion::class);} public function nodes(): HasMany{return $this->hasMany(AutomationNode::class);} public function edges(): HasMany{return $this->hasMany(AutomationEdge::class);} public function runs(): HasMany{return $this->hasMany(AutomationRun::class);} public function schedules(): HasMany{return $this->hasMany(AutomationSchedule::class);}

 /** Canonical order used across the UI, engine and native sync. */
 public const TRIGGERS = ['comment', 'story_reply', 'direct_message'];

 /**
  * Every trigger this automation listens to.
  *
  * New automations store the set in `triggers`; older rows fall back to the
  * single `trigger` column so nothing has to be backfilled before deploying.
  *
  * @return list<string>
  */
 public function triggerValues(): array {
  $stored=is_array($this->triggers)?$this->triggers:[];
  $stored=array_values(array_filter(array_map(static fn($value):string=>is_scalar($value)?(string)$value:'',$stored),static fn(string $value):bool=>in_array($value,self::TRIGGERS,true)));
  if($stored!==[]) return array_values(array_unique($stored));
  $single=$this->trigger instanceof \BackedEnum?$this->trigger->value:(string)$this->trigger;
  return in_array($single,self::TRIGGERS,true)?[$single]:[];
 }

 /** The primary trigger kept in `trigger` for metadata and older screens. */
 public function primaryTrigger(): string {
  $values=$this->triggerValues();
  foreach(self::TRIGGERS as $trigger){if(in_array($trigger,$values,true))return $trigger;}
  return 'comment';
 }

 public function hasTrigger(string $trigger): bool { return in_array($trigger,$this->triggerValues(),true); }

 public function hasMultipleTriggers(): bool { return count($this->triggerValues())>1; }

 /**
  * Zernio's native comment automations accept exactly one trigger. A set of
  * triggers (or a DM trigger) has to run through the local engine instead.
  */
 public function supportsNativeSync(): bool {
  $values=$this->triggerValues();
  return count($values)===1 && in_array($values[0],['comment','story_reply'],true);
 }

 /**
  * Whether an action belongs to the event currently being processed.
  * Actions without a scope stay backwards compatible and run for every trigger.
  */
 public function actionAppliesTo(?array $config,string $trigger): bool {
  $scoped=$config['triggers']??null;
  if(!is_array($scoped)||$scoped===[]) return true;
  return in_array($trigger,array_map('strval',$scoped),true);
 }

 /** Actions that should run for this event, in execution order. */
 public function actionsForTrigger(string $trigger) {
  return $this->actions
      ->filter(fn($action):bool=>$this->actionAppliesTo(is_array($action->config)?$action->config:[],$trigger))
      ->sortBy('sort_order')
      ->values();
 }
}
