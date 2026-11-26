<?php
namespace App\Jobs;
use App\Models\AppSetting;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InstagramAccount;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Automation\ActionExecutor;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\WebhookEventAdapter;
use App\Services\Zernio\ZernioClient;
use App\Support\FollowGate;
use App\Support\FollowStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
class ProcessZernioWebhook implements ShouldQueue {use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;public int $tries=3;public array $backoff=[5,30,120];public function __construct(public int $webhookEventId){} public function handle(WebhookEventAdapter $adapter,AutomationEngine $engine,ActionExecutor $executor,ZernioClient $zernio):void {$event=WebhookEvent::findOrFail($this->webhookEventId);if($event->status==='processed')return;$data=$adapter->adapt($event->payload,$event->event_type);if(!$data){$event->update(['status'=>'ignored','processed_at'=>now()]);return;}$account=InstagramAccount::where('zernio_account_id',$data['account_id']??'')->first();if(!$account){$event->update(['status'=>'ignored','error'=>'Unknown Instagram account','processed_at'=>now()]);return;}
  // Both outbound clients must be pinned: ActionExecutor resolves its own
  // ZernioClient instance, so pinning only $zernio would fall back to the
  // server-wide .env key (or no key at all) in a queue worker.
  $zernio->forUser($account->user);$executor->forUser($account->user);if (($data['trigger'] ?? null) === 'analytics_synced') {
    $metadata = is_array($account->metadata) ? $account->metadata : [];
    $metadata['analytics_synced_at'] = $data['timestamp'] ?? now()->toIso8601String();
    $metadata['analytics_sync'] = is_array($data['sync'] ?? null) ? $data['sync'] : [];
    $account->update(['metadata' => $metadata]);
    $event->update(['status' => 'processed', 'processed_at' => now()]);
    return;
  }

  $this->persistInbox($account,$data);
  if (!empty($data['postback_payload']) && str_starts_with((string)$data['postback_payload'],'follow_gate:')) {
   if ($this->handleFollowGateCallback($account,$data,$engine,$executor,$zernio)) {
    $event->update(['status'=>'processed','processed_at'=>now()]); return;
   }
  }
  // General follow gate: when it is on, every inbound DM is checked, not only
  // messages that happen to match an automation keyword.
  if ($this->globalFollowGate($account,$data,$zernio,$event)) {
   $event->update(['status'=>'processed','processed_at'=>now()]); return;
  }
  $automations=Automation::with(['keywords','actions','account'])->where('instagram_account_id',$account->id)->where('status','active')->orderBy('priority')->get();
  $trigger=(string)($data['trigger']??'');
  foreach($automations as $a){
   if($a->execution_mode!=='local')continue;
   // Multi-trigger automations are handled locally for every selected event.
   if(!$a->hasTrigger($trigger))continue;
   $actions=$a->actionsForTrigger($trigger);
   if($actions->isEmpty())continue;
   $target=$data['trigger']==='story_reply'?($data['story_id']??null):($data['post_id']??null);$context=$data+['target_id'=>$target,'event_id'=>$event->event_id];$eval=$engine->evaluate($a,$data['text']??'',$context);if(!$eval['pass']){$engine->log($a,$context,$eval,'not_matched');continue;}if(!empty($eval['gate_only'])){$gated=$executor->enforceFollowGate($a,$context);$engine->log($a,$context,$eval,$gated?'gated':'not_matched');if($gated)break;continue;}[$allowed,$reason]=$engine->canRun($a,$data['user_id']??null);if(!$allowed){$engine->log($a,$context,$eval,'blocked',$reason);continue;}$run=AutomationRun::create(['automation_id'=>$a->id,'event_id'=>$event->event_id,'status'=>'running','context'=>$context,'started_at'=>now()]);$results=[];foreach($actions as $action){
    $result=$executor->execute($a,$action->toArray(),$context); $results[]=$result;
    if(($result['status']??null)==='waiting_follow') break;
  }
  $waitingFollow=collect($results)->contains(fn($r)=>($r['status']??null)==='waiting_follow');
  $failedAction=collect($results)->first(fn($r)=>($r['status']??null)==='failed');
  $actionFailed=(bool)$failedAction;
  if(!$waitingFollow && !$actionFailed)$engine->markRun($a,$data['user_id']??null);
  $run->update(['status'=>$waitingFollow?'pending':($actionFailed?'failed':'completed'),'result'=>$results,'error'=>$failedAction['error']??null,'finished_at'=>$waitingFollow?null:now()]);
  $logStatus=$waitingFollow?'gated':($actionFailed?'failed':'executed');
  $engine->log($a,$context,$eval,$logStatus,$failedAction['error']??null,$results);if($waitingFollow)break;}$event->update(['status'=>'processed','processed_at'=>now()]);}
 /**
  * Check the follow relationship for every inbound DM when the general gate is
  * enabled. Returns true when the message must stop here (prompt sent, or the
  * contact was already prompted within the last day).
  */
 private function globalFollowGate(InstagramAccount $account,array $data,ZernioClient $zernio,WebhookEvent $event):bool {
  $trigger=(string)($data['trigger']??'');
  if(!in_array($trigger,['direct_message','story_reply'],true))return false;
  if(empty($data['user_id'])||empty($data['conversation_id']))return false;
  $gateMaster=(bool)AppSetting::getValue('follow_gate_enabled',true);
  $gateWithoutKeywords=(bool)AppSetting::getValue('follow_gate_without_keywords',false);
  if(!$gateMaster&&!$gateWithoutKeywords){
   $this->noteFollowGate($event,['decision'=>'disabled','reason'=>'both general follow-gate switches are off']);
   return false;
  }

  $contact=Contact::where('instagram_account_id',$account->id)->where('external_user_id',(string)$data['user_id'])->first();
  $metadata=is_array($contact?->metadata)?$contact->metadata:[];
  $pending=$metadata['follow_gate_pending']??null;
  // Only the global gate's own prompt is allowed to suppress the next global
  // prompt for a day. A pending marker left by a per-automation gate (or a
  // failed attempt) must not silently swallow every DM.
  $recentGlobalPending=is_array($pending)
      &&($pending['scope']??null)==='global'
      // Only a confirmed delivery may suppress the next prompt. Older markers
      // created before the send (or by a failed attempt) must not hold forever.
      &&!empty($pending['sent_at'])
      &&\Illuminate\Support\Carbon::parse($pending['sent_at'])->gt(now()->subDay());
  if($recentGlobalPending){
   $this->noteFollowGate($event,['decision'=>'held','reason'=>'global prompt delivered within the last day','pending_scope'=>'global']);
   return true;
  }

  // The webhook profile is per-message and already fresh; only ask the API when
  // Instagram did not reveal the relationship in the payload.
  $status=FollowStatus::from($data['instagram_profile']??null);
  if($status===null){
   try{
    $status=FollowStatus::from($zernio->getFollowStatus($account->zernio_account_id,(string)$data['user_id'],true));
   }catch(\Throwable $e){
    report($e);
   }
  }
  if($status===null&&$contact&&$contact->is_follower!==null)$status=(bool)$contact->is_follower;

  if($status===true){
   if(is_array($pending)){
    unset($metadata['follow_gate_pending']);
    $contact?->update(['metadata'=>$metadata,'is_follower'=>true]);
   }
   $this->noteFollowGate($event,['decision'=>'skipped','reason'=>'already following']);
   return false;
  }

  $gate=FollowGate::configForSettings([]);
  $token=(string)Str::uuid();
  $message=$status===false?$gate['notFollowingMessage']:$gate['message'];
  $button=[['type'=>'postback','title'=>mb_substr($gate['buttonLabel'],0,20),'payload'=>'follow_gate:'.$token]];

  $metadata['follow_gate_pending']=[
   'token'=>$token,
   'automation_id'=>null,
   'scope'=>'global',
   'context'=>array_filter($data,static fn($value)=>$value!==null),
   'created_at'=>now()->toIso8601String(),
   'sent_at'=>null,
  ];
  $contact=Contact::updateOrCreate(
   ['instagram_account_id'=>$account->id,'external_user_id'=>(string)$data['user_id']],
   ['username'=>$data['username']??null,'full_name'=>$data['full_name']??null,'last_seen_at'=>now()]
  );
  $contact->update(['metadata'=>$metadata]);

  $this->noteFollowGate($event,['decision'=>'prompting','follow_status'=>$status,'message'=>$message]);
  try{
   $zernio->sendMessage($data['conversation_id'],$account->zernio_account_id,['message'=>$message,'buttons'=>$button],(string)Str::uuid());
   $metadata['follow_gate_pending']['sent_at']=now()->toIso8601String();
   $contact->update(['metadata'=>$metadata]);
   $this->noteFollowGate($event,['decision'=>'prompted','follow_status'=>$status]);
  }catch(\Throwable $e){
   report($e);
   // A failed prompt must not hold the conversation forever.
   unset($metadata['follow_gate_pending']);
   $contact->update(['metadata'=>$metadata]);
   $this->noteFollowGate($event,['decision'=>'send_failed','follow_status'=>$status,'error'=>$e->getMessage()]);
   $event->update(['error'=>'follow_gate_send_failed: '.$e->getMessage()]);
   return false;
  }

  return true;
 }

 private function noteFollowGate(WebhookEvent $event,array $note):void {
  $payload=is_array($event->payload)?$event->payload:[];
  $payload['_follow_gate']=array_merge(['at'=>now()->toIso8601String()],$note);
  $event->update(['payload'=>$payload]);
 }

 private function persistInbox(InstagramAccount $account,array $d):void {if(empty($d['conversation_id']))return;$contact=null;if(!empty($d['user_id'])){$profile=is_array($d['instagram_profile']??null)?$d['instagram_profile']:[];$status=FollowStatus::from($d['instagram_profile']??null);$attributes=['last_seen_at'=>now()];if(!empty($d['username']))$attributes['username']=$d['username'];if(!empty($d['full_name']))$attributes['full_name']=$d['full_name'];
   // Persist a known follower value only; a payload without the profile must not erase it.
   if($status!==null)$attributes['is_follower']=$status;
   if(array_key_exists('followerCount',$profile))$attributes['follower_count']=$profile['followerCount'];
   if(array_key_exists('isVerified',$profile))$attributes['is_verified']=$profile['isVerified'];
   $contact=Contact::updateOrCreate(['instagram_account_id'=>$account->id,'external_user_id'=>(string)$d['user_id']],$attributes);
   if($profile!==[]){$metadata=is_array($contact->metadata)?$contact->metadata:[];$contact->update(['metadata'=>array_merge($metadata,$profile)]);}}$conv=Conversation::updateOrCreate(['zernio_conversation_id'=>$d['conversation_id']],['instagram_account_id'=>$account->id,'contact_id'=>$contact?->id,'last_message_at'=>now(),'last_inbound_at'=>now()] );$conv->update(['unread_count'=>$conv->unread_count+1,'last_message_at'=>now(),'last_inbound_at'=>now()]);if(!empty($d['event_id']))Message::firstOrCreate(['external_message_id'=>$d['message_id']??$d['event_id']],['conversation_id'=>$conv->id,'direction'=>'inbound','type'=>'text','text'=>$d['text']??null,'status'=>'received','source'=>'zernio','attachments'=>$d['attachments']??[],'metadata'=>$d,'sent_at'=>now()]);}
 private function handleFollowGateCallback(InstagramAccount $account,array $data,AutomationEngine $engine,ActionExecutor $executor,ZernioClient $zernio):bool {
  if(empty($data['user_id'])||empty($data['conversation_id'])) return false;
  $contact=Contact::where('instagram_account_id',$account->id)->where('external_user_id',(string)$data['user_id'])->first();
  $pending=data_get($contact?->metadata,'follow_gate_pending');
  if(!is_array($pending)||empty($pending['token'])||(string)$data['postback_payload']!=='follow_gate:'.$pending['token']) return false;
  $automation=Automation::with(['keywords','actions','account'])->find((int)($pending['automation_id']??0));
  // The postback itself grants Instagram DM consent. Meta/Zernio can need a short
  // moment to expose the refreshed follow relationship, so do not fail the job on
  // a transient null/transport response.
  $follow=null;
  $followError=null;
  for($attempt=1;$attempt<=3;$attempt++){
    try{
      $follow=$zernio->getFollowStatus($account->zernio_account_id,(string)$data['user_id'],true);
      if(FollowStatus::from($follow)!==null)break;
    }catch(\Throwable $e){
      $followError=$e;
    }
    if($attempt<3)usleep(500000);
  }

  $isFollower=FollowStatus::from($follow);
  if($isFollower!==true){
    $gate=$automation?FollowGate::config($automation):FollowGate::configForSettings([]);
    $label=$gate['buttonLabel'];
    $message=$isFollower===false?$gate['notFollowingMessage']:$gate['unknownMessage'];
    $zernio->sendMessage($data['conversation_id'],$account->zernio_account_id,['message'=>$message,'buttons'=>[['type'=>'postback','title'=>mb_substr($label,0,20),'payload'=>'follow_gate:'.$pending['token']]]],(string)Str::uuid());
    return true;
  }
  if(!$automation||$automation->status!=='active'){
   // Global gate without a specific automation: confirmation is enough. Clear
   // the pending marker so the next message is handled normally.
   $metadata=is_array($contact->metadata)?$contact->metadata:[];unset($metadata['follow_gate_pending']);$contact->update(['metadata'=>$metadata,'is_follower'=>true]);
   return true;
  }
  $context=is_array($pending['context']??null)?$pending['context']:[];
  $originalTrigger=(string)($context['trigger']??'');
  // The postback carries the live conversation/user data; the stored context
  // only holds the original event (which may have no conversation yet).
  $context=array_merge($context,$data);
  if($originalTrigger!=='')$context['trigger']=$originalTrigger;
  $context['follow_gate_bypass']=true; $context['instagram_profile']['isFollower']=true;
  $trigger=(string)($context['trigger']??'');
  $actions=$automation->actionsForTrigger($trigger);
  if($actions->isEmpty())return true;
  [$allowed,$reason]=$engine->canRun($automation,$data['user_id']??null);
  if(!$allowed){$engine->log($automation,$context,['keywords'=>['pass'=>true]],'blocked',$reason);return true;}
  $results=[];
  foreach($actions as $action){$result=$executor->execute($automation,$action->toArray(),$context);$results[]=$result;if(($result['status']??null)==='waiting_follow')break;}
  if(!collect($results)->contains(fn($r)=>($r['status']??null)==='waiting_follow')){
    $failedAction=collect($results)->first(fn($r)=>($r['status']??null)==='failed');
    if(!$failedAction)$engine->markRun($automation,$data['user_id']??null);
    $engine->log($automation,$context,['keywords'=>['pass'=>true]],$failedAction?'failed':'executed',$failedAction['error']??null,$results);
    $metadata=is_array($contact->metadata)?$contact->metadata:[];unset($metadata['follow_gate_pending']);$contact->update(['metadata'=>$metadata,'is_follower'=>true]);
  }
  return true;
 }

 public function failed(\Throwable $e):void{WebhookEvent::whereKey($this->webhookEventId)->update(['status'=>'failed','error'=>$e->getMessage()]);}}
