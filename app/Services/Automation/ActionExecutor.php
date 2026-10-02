<?php
namespace App\Services\Automation;
use App\Enums\AutomationAction;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Str;
class ActionExecutor {
 public function __construct(private ZernioClient $zernio, private TemplateRenderer $renderer) {}
 public function execute(Automation $a,array $action,array $context):array { $type=AutomationAction::tryFrom($action['action']??'');$cfg=$action['config']??[];if(!$type)return ['action'=>$action['action']??null,'status'=>'skipped','reason'=>'unknown_action'];$message=$this->renderer->render($cfg['message']??'',$context);try{return match($type){AutomationAction::PublicReply=>$this->publicReply($a,$context,$message),AutomationAction::PrivateReply=>$this->privateReply($a,$context,$message,$cfg),AutomationAction::DirectMessage=>$this->directMessage($a,$context,$message,$cfg),AutomationAction::HideComment=>$this->hide($a,$context),AutomationAction::AddTag=>$this->addTag($a,$context,$cfg)};}catch(\Throwable $e){return ['action'=>$type->value,'status'=>'failed','error'=>$e->getMessage()];} }
 private function publicReply(Automation $a,array $c,string $m):array {if(!$m||empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'public_reply','status'=>'skipped','reason'=>'missing_data'];$r=$this->zernio->replyToComment($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$m);return ['action'=>'public_reply','status'=>'sent','response'=>$r];}
 private function privateReply(Automation $a,array $c,string $m,array $cfg):array {if(!$m||empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'private_reply','status'=>'skipped','reason'=>'missing_data'];$body=['message'=>$m];if(!empty($cfg['buttons']))$body['buttons']=$cfg['buttons'];elseif(!empty($cfg['quickReplies']))$body['quickReplies']=$cfg['quickReplies'];$r=$this->zernio->privateReply($c['post_id'],$c['comment_id'],$a->account->zernio_account_id,$body);return ['action'=>'private_reply','status'=>'sent','response'=>$r];}
 private function directMessage(Automation $a,array $c,string $m,array $cfg):array {
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
 private function hide(Automation $a,array $c):array {if(empty($c['post_id'])||empty($c['comment_id']))return ['action'=>'hide_comment','status'=>'skipped'];$r=$this->zernio->hideComment($c['post_id'],$c['comment_id'],$a->account->zernio_account_id);return ['action'=>'hide_comment','status'=>'sent','response'=>$r];}
 private function addTag(Automation $a,array $c,array $cfg):array {$user=$c['user_id']??null;$account=$a->account;if(!$user||empty($cfg['tag']))return ['action'=>'add_tag','status'=>'skipped'];$contact=Contact::firstOrCreate(['instagram_account_id'=>$account->id,'external_user_id'=>$user],['username'=>$c['username']??null,'full_name'=>$c['full_name']??null]);$tag=Tag::firstOrCreate(['slug'=>Str::slug($cfg['tag'])],['name'=>$cfg['tag']]);$contact->tags()->syncWithoutDetaching([$tag->id]);return ['action'=>'add_tag','status'=>'done','tag'=>$tag->name];}
}
