<?php
namespace App\Services\Automation;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationUserCooldown;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
class AutomationEngine {
 public function __construct(private KeywordMatcher $matcher, private BusinessHours $hours, private RateLimiter $limiter) {}
 public function evaluate(Automation $a,string $text,array $context=[]):array {
  $keyword=$this->matcher->match($a,$text);
  $targetOk=true; if($a->target_id){$key=$a->target_type==='story'?'story_id':($a->target_type==='post'?'post_id':null);$targetOk=$key?(($context[$key]??null)===$a->target_id):(($context['target_id']??null)===$a->target_id);}
  $triggerOk=($context['trigger']??null)===($a->trigger?->value ?? $a->trigger);
  $aud=$this->audience($a,$context);
  $global=(bool)AppSetting::getValue('automation_global_enabled',true) && $a->global_enabled;
  $business=!$a->business_hours_only || $this->hours->allowed();
  return ['pass'=>$keyword['pass']&&$targetOk&&$triggerOk&&$aud['pass']&&$global&&$business,'keywords'=>$keyword,'target_match'=>$targetOk,'trigger_match'=>$triggerOk,'audience'=>$aud,'global_enabled'=>$global,'business_hours'=>$business];
 }
 private function audience(Automation $a,array $c):array { $cfg=$a->audience??[]; $profile=$c['instagram_profile']??[]; $status=$profile['isFollower'] ?? null; $wanted=$cfg['followerStatus']??'any'; if($wanted==='any')return ['pass'=>true,'status'=>$status]; if($status===null){return ['pass'=>($cfg['whenUnknown']??'send')==='send','status'=>null,'unknown'=>true];} if($wanted==='follower' && $status!==true)return ['pass'=>false,'status'=>$status]; if($wanted==='non_follower' && $status!==false)return ['pass'=>false,'status'=>$status]; if(!empty($cfg['minFollowerCount']) && (int)($profile['followerCount']??0)<(int)$cfg['minFollowerCount'])return ['pass'=>false,'status'=>$status]; return ['pass'=>true,'status'=>$status]; }
 public function canRun(Automation $a,?string $userKey):array { if(!$userKey)return [true,null]; $row=AutomationUserCooldown::firstOrCreate(['automation_id'=>$a->id,'user_key'=>$userKey],['daily_count'=>0,'count_date'=>now()->toDateString()]); if(!$row->count_date || $row->count_date->toDateString()!==now()->toDateString()){$row->update(['daily_count'=>0,'count_date'=>now()->toDateString()]);$row->refresh();} if($a->max_per_user_day!==null && $row->daily_count >= $a->max_per_user_day)return [false,'daily_limit']; if($a->cooldown_seconds>0 && $row->last_executed_at && $row->last_executed_at->gt(now()->subSeconds($a->cooldown_seconds)))return [false,'cooldown']; if(!$this->limiter->allow('automation:'.$a->id.':global',(int)($a->settings['rate_limit_per_minute']??30),60))return [false,'rate_limit']; return [true,null]; }
 public function markRun(Automation $a,?string $userKey):void {if(!$userKey)return; AutomationUserCooldown::updateOrCreate(['automation_id'=>$a->id,'user_key'=>$userKey],['last_executed_at'=>now(),'daily_count'=>DB::raw('daily_count + 1'),'count_date'=>now()->toDateString()]);}
 public function log(Automation $a,array $context,array $evaluation,string $status='matched',?string $error=null,array $actions=[]):AutomationLog{return $a->logs()->create(['event_type'=>$context['event_type']??null,'event_id'=>$context['event_id']??null,'status'=>$status,'input'=>$context,'matched_keywords'=>$evaluation['keywords']['matched']??[],'conditions'=>$evaluation,'actions_result'=>$actions,'error'=>$error]);}
}
