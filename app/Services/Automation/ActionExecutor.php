<?php
namespace App\Services\Automation;
use App\Enums\AutomationAction;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\AppSetting;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Str;
class ActionExecutor {
 public function __construct(private ZernioClient $zernio, private TemplateRenderer $renderer) {}
 public function execute(Automation $a,array $action,array $context):array { $type=AutomationAction::tryFrom($action['action']??'');$cfg=$action['config']??[];if(!$type)return ['action'=>$action['action']??null,'status'=>'skipped','reason'=>'unknown_action'];$message=$this->renderer->render($cfg['message']??'',$context);try{return match($type){AutomationAction::PublicReply=>$this->publicReply($a,$context,$message),AutomationAction::PrivateReply=>$this->privateReply($a,$context,$message,$cfg),AutomationAction::DirectMessage=>$this->directMessage($a,$context,$message,$cfg),AutomationAction::HideComment=>$this->hide($a,$context),AutomationAction::AddTag=>$this->addTag($a,$context,$cfg)};}catch(\Throwable $e){return ['action'=>$type->value,'status'=>'failed','error'=>$e->getMessage()];} }
 private function publicReply(Automation $a,array $c,string $m):array {if(!$m||empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'public_reply','status'=>'skipped','reason'=>'missing_data'];$r=$this->zernio->replyToComment($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$m);return ['action'=>'public_reply','status'=>'sent','response'=>$r];}
 private function privateReply(Automation $a,array $c,string $m,array $cfg):array {if(!$m||empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'private_reply','status'=>'skipped','reason'=>'missing_data'];$body=['message'=>$m];if(!empty($cfg['buttons']))$body['buttons']=$cfg['buttons'];elseif(!empty($cfg['quickReplies']))$body['quickReplies']=$cfg['quickReplies'];$r=$this->zernio->privateReply($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$body);return ['action'=>'private_reply','status'=>'sent','response'=>$r];}
 private function directMessage(Automation $a,array $c,string $m,array $cfg):array {
  if (empty($c['follow_gate_bypass']) && (bool) AppSetting::getValue('follow_gate_enabled', true) && !empty($c['user_id'])) {
    try {
      $follow = $this->zernio->getFollowStatus($a->account->zernio_account_id, (string) $c['user_id'], true);
    } catch (\Throwable $e) {
      $follow = ['isFollower' => data_get($c, 'instagram_profile.isFollower')];
    }
    if (data_get($follow, 'isFollower') !== true) {
      $token=$this->storeFollowGatePending($a, $c);
      $this->sendFollowGate($a, $c, $token);
      return ['action'=>'direct_message','status'=>'waiting_follow','reason'=>'follow_required'];
    }
  }

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

 private function sendFollowGate(Automation $a,array $c,string $token):void {
  $message=(string) AppSetting::getValue('follow_gate_message','دوست خوبم حتماً باید پیج رو فالو داشته باشی تا بتونیم بهت پیام بدیم.');
  $label=(string) AppSetting::getValue('follow_gate_button_label','فالو کردم ✓');
  $button=[['type'=>'postback','title'=>mb_substr($label,0,20),'payload'=>'follow_gate:'.(string)$token]];
  if (!empty($c['conversation_id'])) {
    $this->zernio->sendMessage($c['conversation_id'],$a->account->zernio_account_id,['message'=>$message,'buttons'=>$button],(string)Str::uuid());
  }
 }

 private function hide(Automation $a,array $c):array {if(empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'hide_comment','status'=>'skipped'];$r=$this->zernio->hideComment($c['post_id'],$c['comment_id'],$a->account->zernio_account_id);return ['action'=>'hide_comment','status'=>'sent','response'=>$r];}
 private function addTag(Automation $a,array $c,array $cfg):array {$user=$c['user_id']??null;$account=$a->account;if(!$user||empty($cfg['tag']))return ['action'=>'add_tag','status'=>'skipped'];$contact=Contact::firstOrCreate(['instagram_account_id'=>$account->id,'external_user_id'=>$user],['username'=>$c['username']??null,'full_name'=>$c['full_name']??null]);$tag=Tag::firstOrCreate(['slug'=>Str::slug($cfg['tag'])],['name'=>$cfg['tag']]);$contact->tags()->syncWithoutDetaching([$tag->id]);return ['action'=>'add_tag','status'=>'done','tag'=>$tag->name];}
}
