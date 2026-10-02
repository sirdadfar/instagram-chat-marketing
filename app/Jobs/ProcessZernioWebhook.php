<?php
namespace App\Jobs;
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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ProcessZernioWebhook implements ShouldQueue {use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;public int $tries=3;public array $backoff=[5,30,120];public function __construct(public int $webhookEventId){} public function handle(WebhookEventAdapter $adapter,AutomationEngine $engine,ActionExecutor $executor):void {$event=WebhookEvent::findOrFail($this->webhookEventId);if($event->status==='processed')return;$data=$adapter->adapt($event->payload,$event->event_type);if(!$data){$event->update(['status'=>'ignored','processed_at'=>now()]);return;}$account=InstagramAccount::where('zernio_account_id',$data['account_id']??'')->first();if(!$account){$event->update(['status'=>'ignored','error'=>'Unknown Instagram account','processed_at'=>now()]);return;}$this->persistInbox($account,$data);$automations=Automation::with(['keywords','actions','account'])->where('instagram_account_id',$account->id)->where('status','active')->orderBy('priority')->get();foreach($automations as $a){if($a->execution_mode!=='local')continue;$target=$data['trigger']==='story_reply'?($data['story_id']??null):($data['post_id']??null);$context=$data+['target_id'=>$target,'event_id'=>$event->event_id];$eval=$engine->evaluate($a,$data['text']??'',$context);if(!$eval['pass']){$engine->log($a,$context,$eval,'not_matched');continue;}[$allowed,$reason]=$engine->canRun($a,$data['user_id']??null);if(!$allowed){$engine->log($a,$context,$eval,'blocked',$reason);continue;}$run=AutomationRun::create(['automation_id'=>$a->id,'event_id'=>$event->event_id,'status'=>'running','context'=>$context,'started_at'=>now()]);$results=[];foreach($a->actions as $action)$results[]=$executor->execute($a,$action->toArray(),$context);$engine->markRun($a,$data['user_id']??null);$run->update(['status'=>'completed','result'=>$results,'finished_at'=>now()]);$engine->log($a,$context,$eval,'executed',null,$results);}$event->update(['status'=>'processed','processed_at'=>now()]);}
 private function persistInbox(InstagramAccount $account,array $d):void {if(empty($d['conversation_id']))return;$contact=null;if(!empty($d['user_id'])){$contact=Contact::updateOrCreate(['instagram_account_id'=>$account->id,'external_user_id'=>$d['user_id']],['username'=>$d['username']??null,'full_name'=>$d['full_name']??null,'is_follower'=>data_get($d,'instagram_profile.isFollower'),'follower_count'=>data_get($d,'instagram_profile.followerCount'),'is_verified'=>data_get($d,'instagram_profile.isVerified'),'last_seen_at'=>now(),'metadata'=>$d['instagram_profile']??[]]);}$conv=Conversation::updateOrCreate(['zernio_conversation_id'=>$d['conversation_id']],['instagram_account_id'=>$account->id,'contact_id'=>$contact?->id,'last_message_at'=>now(),'last_inbound_at'=>now()] );$conv->update(['unread_count'=>$conv->unread_count+1,'last_message_at'=>now(),'last_inbound_at'=>now()]);if(!empty($d['event_id']))Message::firstOrCreate(['external_message_id'=>$d['message_id']??$d['event_id']],['conversation_id'=>$conv->id,'direction'=>'inbound','type'=>'text','text'=>$d['text']??null,'status'=>'received','source'=>'zernio','attachments'=>$d['attachments']??[],'metadata'=>$d,'sent_at'=>now()]);}
 public function failed(\Throwable $e):void{WebhookEvent::whereKey($this->webhookEventId)->update(['status'=>'failed','error'=>$e->getMessage()]);}}
