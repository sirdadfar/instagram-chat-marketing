<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessZernioWebhook;
use App\Models\InstagramAccount;
use App\Models\WebhookEvent;
use App\Services\Automation\WebhookEventAdapter;
use App\Support\Credentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class WebhookController extends Controller {
 public function zernio(Request $r):JsonResponse {
  $raw=$r->getContent();$payload=[];
  try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){return response()->json(['error'=>'Invalid JSON'],400);}
  if(!is_array($payload))return response()->json(['error'=>'Invalid JSON'],400);

  // Multi-tenant signature routing: pick the signing secret of the page owner
  // referenced in the raw payload; legacy deployments keep the server-wide key.
  $accountId=WebhookEventAdapter::accountId($payload);
  $account=$accountId!==null?InstagramAccount::where('zernio_account_id',$accountId)->first():null;
  $ownerSecret=$account&&$account->user?Credentials::userWebhookSecret($account->user):null;
  $globalSecret=Credentials::zernioWebhookSecret();
  $sig=$r->header('X-Zernio-Signature')??$r->header('X-Late-Signature');
  if(!$ownerSecret&&!$globalSecret)return response()->json(['error'=>'Webhook secret is not configured'],503);

  // Accept either secret. Tenants can store a personal secret while the
  // endpoint that actually delivers their events is still registered with the
  // global one; rejecting the delivery would silently drop every event.
  $valid=false;
  foreach(array_unique(array_filter([$ownerSecret,$globalSecret])) as $candidate){
   if($sig&&hash_equals(hash_hmac('sha256',$raw,$candidate),$sig)){$valid=true;break;}
  }
  if(!$valid)return response()->json(['error'=>'Invalid signature'],401);

  $eventId=$r->header('X-Zernio-Event-Id')??$r->header('X-Late-Event-Id')??($payload['id']??null);
  $eventType=$r->header('X-Zernio-Event')??($payload['event']??'unknown');
  if(!$eventId)return response()->json(['error'=>'Missing event id'],400);

  $event=WebhookEvent::firstOrCreate(['provider'=>'zernio','event_id'=>$eventId],[
   'event_type'=>$eventType,
   'instagram_account_id'=>$account?->id,
   'payload'=>$payload,
   'status'=>'pending',
  ]);
  if(!$event->wasRecentlyCreated){
   // Older rows were stored before events knew their page. Fill it lazily so
   // the tenant can see deliveries that are still being retried.
   if(!$event->instagram_account_id&&$account)$event->update(['instagram_account_id'=>$account->id]);
   // If the first attempt could not resolve the page, a later Zernio retry may
   // now resolve it. Requeue once the account exists instead of dropping it.
   if($account&&$event->status==='ignored'&&$event->error==='Unknown Instagram account'){
    $event->update(['status'=>'pending','error'=>null,'processed_at'=>null]);
    ProcessZernioWebhook::dispatch($event->id);
    return response()->json(['ok'=>true,'requeued'=>true],202);
   }
   return response()->json(['ok'=>true,'duplicate'=>true]);
  }
  ProcessZernioWebhook::dispatch($event->id);
  return response()->json(['ok'=>true],202);
 }
}
