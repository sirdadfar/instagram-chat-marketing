<?php
namespace App\Services\Automation;
class WebhookEventAdapter {
 public function adapt(array $p,string $event):?array {return match($event){'comment.received'=>$this->comment($p),'message.received'=>$this->message($p),default=>null};}
 private function comment(array $p):array {$c=data_get($p,'comment',[]);$author=data_get($c,'author',[]);$account=data_get($p,'account.accountId')??data_get($p,'account.id');return ['event_type'=>'comment.received','event_id'=>$p['id']??null,'trigger'=>'comment','account_id'=>$account,'post_id'=>data_get($c,'postId')??data_get($p,'post.id')??data_get($p,'postId'),'comment_id'=>data_get($c,'id')??data_get($p,'commentId'),'text'=>(string)(data_get($c,'text')??data_get($c,'message')??''),'username'=>data_get($author,'username')??'','full_name'=>data_get($author,'name')??'','user_id'=>data_get($author,'id')??data_get($author,'instagramId'),'instagram_profile'=>data_get($author,'instagramProfile',[]),'conversation_id'=>data_get($c,'conversationId'),'account_username'=>data_get($p,'account.username'),'post_url'=>data_get($p,'post.url')??data_get($c,'postUrl')];}
 private function message(array $p):?array {
  $m=data_get($p,'message',[]);
  if(data_get($m,'isOwnAccount')===true||data_get($m,'sender.isOwnAccount')===true)return null;
  $sender=data_get($m,'sender',[]);
  $story=data_get($m,'storyReply',[]);
  $account=data_get($p,'account.accountId')??data_get($p,'account.id');
  $metadata=data_get($m,'metadata',data_get($p,'metadata',[]));
  $postbackPayload=data_get($metadata,'postbackPayload')
      ??data_get($metadata,'postback_payload')
      ??data_get($metadata,'callbackData')
      ??data_get($m,'postback.payload')
      ??data_get($m,'postbackPayload')
      ??data_get($metadata,'buttonPayload')
      ??data_get($p,'postback.payload')
      ??data_get($p,'postbackPayload');
  $postbackTitle=data_get($metadata,'postbackTitle')
      ??data_get($metadata,'postback_title')
      ??data_get($m,'postback.title')
      ??data_get($m,'postbackTitle')
      ??data_get($metadata,'quickReplyTitle')
      ??data_get($p,'postback.title')
      ??data_get($p,'postbackTitle');
  $text=(string)(data_get($m,'text')??data_get($m,'message')??'');
  return ['event_type'=>'message.received','event_id'=>$p['id']??null,'trigger'=>!empty($story)?'story_reply':'direct_message','account_id'=>$account,'conversation_id'=>data_get($p,'conversation.id')??data_get($p,'conversationId')??data_get($m,'conversationId'),'text'=>$text,'username'=>data_get($sender,'username')??'','full_name'=>data_get($sender,'name')??'','user_id'=>data_get($sender,'id'),'story_id'=>data_get($story,'storyId'),'story_url'=>data_get($story,'storyUrl'),'instagram_profile'=>data_get($sender,'instagramProfile',[]),'account_username'=>data_get($p,'account.username'),'attachments'=>data_get($m,'attachments',[]),'message_id'=>data_get($m,'id')??data_get($p,'messageId'),'postback_payload'=>$postbackPayload,'postback_title'=>$postbackTitle];
}
}
