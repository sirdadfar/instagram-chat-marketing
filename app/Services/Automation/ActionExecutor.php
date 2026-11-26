<?php
namespace App\Services\Automation;
use App\Enums\AutomationAction;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\AppSetting;
use App\Services\Zernio\ZernioClient;
use App\Support\FollowGate;
use App\Support\FollowStatus;
use Illuminate\Support\Str;
class ActionExecutor {
 public function __construct(private ZernioClient $zernio, private TemplateRenderer $renderer) {}
 public function execute(Automation $a,array $action,array $context):array { $type=AutomationAction::tryFrom($action['action']??'');$cfg=$action['config']??[];if(!$type)return ['action'=>$action['action']??null,'status'=>'skipped','reason'=>'unknown_action'];$message=$this->renderer->render($cfg['message']??'',$context);try{return match($type){AutomationAction::PublicReply=>$this->publicReply($a,$context,$message),AutomationAction::PrivateReply=>$this->privateReply($a,$context,$message,$cfg),AutomationAction::DirectMessage=>$this->directMessage($a,$context,$message,$cfg),AutomationAction::HideComment=>$this->hide($a,$context),AutomationAction::AddTag=>$this->addTag($a,$context,$cfg)};}catch(\Throwable $e){return ['action'=>$type->value,'status'=>'failed','error'=>$e->getMessage()];} }
 private function publicReply(Automation $a,array $c,string $m):array {if(!$m||empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'public_reply','status'=>'skipped','reason'=>'missing_data'];$r=$this->zernio->replyToComment($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$m);return ['action'=>'public_reply','status'=>'sent','response'=>$r];}
 private function privateReply(Automation $a,array $c,string $m,array $cfg):array {
  if(!$m||empty($c['conversation_id'])&&(empty($c['post_id'])||empty($c['comment_id'])))return ['action'=>'private_reply','status'=>'skipped','reason'=>'missing_data'];
  // The follow gate always runs before the real answer, for every trigger.
  if($this->followGateHold($a,$c))return ['action'=>'private_reply','status'=>'waiting_follow','reason'=>'follow_required'];
  $body=['message'=>$m];if(!empty($cfg['buttons']))$body['buttons']=$cfg['buttons'];elseif(!empty($cfg['quickReplies']))$body['quickReplies']=$cfg['quickReplies'];
  // After the gate is confirmed a conversation exists: send normally instead of
  // consuming the comment's single private-reply allowance twice.
  if(!empty($c['conversation_id'])){$r=$this->zernio->sendMessage($c['conversation_id'],$a->account->zernio_account_id,$body,(string)Str::uuid());}
  else{$r=$this->zernio->privateReply($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$body);}
  return ['action'=>'private_reply','status'=>'sent','response'=>$r];
 }
 private function directMessage(Automation $a,array $c,string $m,array $cfg):array {
  if($this->followGateHold($a,$c))return ['action'=>'direct_message','status'=>'waiting_follow','reason'=>'follow_required'];

  $hasAttachment = !empty($cfg['attachmentUrl']);
  $hasInteractive = !empty($cfg['template']) || !empty($cfg['buttons']) || !empty($cfg['quickReplies']);
  if (empty($c['conversation_id']) || ($m === '' && !$hasAttachment && !$hasInteractive)) {
    return ['action'=>'direct_message','status'=>'skipped','reason'=>'missing_data'];
  }
  $body=[];
  if($m!=='') $body['message']=$m;
  if($hasAttachment){
    $body['attachmentUrl']=$cfg['attachmentUrl'];
    $body['attachmentType']=$cfg['attachmentType']??'file';
    if(!empty($cfg['attachmentName'])) $body['attachmentName']=$cfg['attachmentName'];
  }
  foreach(['buttons','quickReplies','template','messageTag','messagingType','linkPreview'] as $k) if(!empty($cfg[$k])) $body[$k]=$cfg[$k];
  $r=$this->zernio->sendMessage($c['conversation_id'],$a->account->zernio_account_id,$body,(string)Str::uuid());
  return ['action'=>'direct_message','status'=>'sent','attachmentType'=>$body['attachmentType']??null,'response'=>$r];
 }
 /**
  * Keyword-less follow gate: sends only the follow prompt to non-followers.
  * Returns true when a prompt was actually sent (the main DM stays pending).
  */
 public function enforceFollowGate(Automation $a,array $c):bool {
  if (empty($c['user_id'])) return false;
  if (empty($c['conversation_id']) && (empty($c['post_id']) || empty($c['comment_id']))) return false;
  if (! FollowGate::enabled($a) || FollowGate::targetsNonFollowers($a)) return false;
  $status = $this->resolveFollowerStatus($a, $c);
  if ($status === true) return false;
  // Do not nag: one pending prompt per contact/automation per day.
  $contact = Contact::where('instagram_account_id',$a->account->id)->where('external_user_id',(string)$c['user_id'])->first();
  if ($this->hasRecentFollowGatePrompt($a, $c, $contact)) return false;
  $token = $this->storeFollowGatePending($a, $c);
  $this->sendFollowGate($a, $c, $token, $status);
  return true;
 }
 /**
  * Hold a real message until the contact follows the page.
  *
  * Returns true when the main action must stop; the prompt is sent at most
  * once per contact/automation/day and reused by the postback handler.
  */
 private function followGateHold(Automation $a,array $c):bool {
  if (!empty($c['follow_gate_bypass']) || empty($c['user_id'])) return false;
  if (! FollowGate::enabled($a) || FollowGate::targetsNonFollowers($a)) return false;
  $status = $this->resolveFollowerStatus($a, $c);
  if ($status === true) return false;
  if (!$this->hasRecentFollowGatePrompt($a, $c)) {
   $token = $this->storeFollowGatePending($a, $c);
   $this->sendFollowGate($a, $c, $token, $status);
  }
  return true;
 }
 /**
  * Tri-state follower lookup: fresh API value wins, then the webhook profile,
  * then the last value persisted on the contact.
  */
 private function resolveFollowerStatus(Automation $a,array $c):?bool {
  $profile = FollowStatus::from($c['instagram_profile'] ?? null);
  if (!empty($c['user_id'])) {
   try {
    $fresh = FollowStatus::from($this->zernio->getFollowStatus($a->account->zernio_account_id, (string) $c['user_id'], true));
    if ($fresh !== null) return $fresh;
   } catch (\Throwable $e) {
    // Transport/consent errors are unknown, not "not a follower".
   }
   if ($profile !== null) return $profile;
   $contact = Contact::where('instagram_account_id',$a->account->id)->where('external_user_id',(string)$c['user_id'])->first();
   if ($contact && $contact->is_follower !== null) return (bool) $contact->is_follower;
   return null;
  }
  return $profile;
 }
 private function hasRecentFollowGatePrompt(Automation $a,array $c,?Contact $contact=null):bool {
  if (empty($c['user_id'])) return false;
  $contact ??= Contact::where('instagram_account_id',$a->account->id)->where('external_user_id',(string)$c['user_id'])->first();
  $pending = data_get($contact?->metadata, 'follow_gate_pending');
  return is_array($pending)
      && (int) ($pending['automation_id'] ?? 0) === (int) $a->id
      && !empty($pending['created_at'])
      && \Illuminate\Support\Carbon::parse($pending['created_at'])->gt(now()->subDay());
 }
 private function storeFollowGatePending(Automation $a,array $c):string {
  if (empty($c['user_id'])) return ''; 
  $contact = Contact::updateOrCreate(
    ['instagram_account_id'=>$a->account->id,'external_user_id'=>(string)$c['user_id']],
    ['username'=>$c['username']??null,'full_name'=>$c['full_name']??null,'last_seen_at'=>now()]
  );
  $token=(string) Str::uuid();
  $metadata=is_array($contact->metadata)?$contact->metadata:[];
  $metadata['follow_gate_pending']=[
    'token'=>$token,
    'automation_id'=>$a->id,
    'context'=>array_filter($c, static fn($v)=>$v!==null),
    'created_at'=>now()->toIso8601String(),
  ];
  $contact->update(['metadata'=>$metadata]);
  return $token;
 }

 private function sendFollowGate(Automation $a,array $c,string $token,?bool $status):void {
  $gate=FollowGate::config($a);
  $message=$status === false ? $gate['notFollowingMessage'] : $gate['unknownMessage'];
  $label=$gate['buttonLabel'];
  $button=[['type'=>'postback','title'=>mb_substr($label,0,20),'payload'=>'follow_gate:'.(string)$token]];
  $body=['message'=>$message,'buttons'=>$button];
  if (!empty($c['conversation_id'])) {
    $this->zernio->sendMessage($c['conversation_id'],$a->account->zernio_account_id,$body,(string)Str::uuid());
  } elseif (!empty($c['post_id']) && !empty($c['comment_id'])) {
    // Comments usually have no conversation yet; the prompt itself opens the
    // DM thread and its postback brings the conversation id back.
    $this->zernio->privateReply($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$body);
  }
 }

 private function hide(Automation $a,array $c):array {if(empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'hide_comment','status'=>'skipped'];$r=$this->zernio->hideComment($c['post_id'],$c['comment_id'],$a->account->zernio_account_id);return ['action'=>'hide_comment','status'=>'sent','response'=>$r];}
 private function addTag(Automation $a,array $c,array $cfg):array {$user=$c['user_id']??null;$account=$a->account;if(!$user||empty($cfg['tag']))return ['action'=>'add_tag','status'=>'skipped'];$contact=Contact::firstOrCreate(['instagram_account_id'=>$account->id,'external_user_id'=>$user],['username'=>$c['username']??null,'full_name'=>$c['full_name']??null]);$tag=Tag::firstOrCreate(['slug'=>Str::slug($cfg['tag'])],['name'=>$cfg['tag']]);$contact->tags()->syncWithoutDetaching([$tag->id]);return ['action'=>'add_tag','status'=>'done','tag'=>$tag->name];}
}
