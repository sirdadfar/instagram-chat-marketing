<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessZernioWebhook;
use App\Models\InstagramAccount;
use App\Models\WebhookEvent;
use App\Support\Credentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class WebhookController extends Controller {public function zernio(Request $r):JsonResponse {$raw=$r->getContent();$payload=[];
try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){return response()->json(['error'=>'Invalid JSON'],400);}
// Multi-tenant signature routing: pick the signing secret of the page owner
// referenced in the raw payload; legacy deployments keep the server-wide key.
$ownerSecret=null;$accountId=data_get($payload,'account.accountId')??data_get($payload,'account.id')??data_get($payload,'accountId')??data_get($payload,'account_id');
if(is_string($accountId)||is_numeric($accountId)){$owner=InstagramAccount::where('zernio_account_id',(string)$accountId)->first();$ownerSecret=$owner&&$owner->user?Credentials::userWebhookSecret($owner->user):null;}
$secret=$ownerSecret??Credentials::zernioWebhookSecret();
if(!$secret)return response()->json(['error'=>'Webhook secret is not configured'],503);
$sig=$r->header('X-Zernio-Signature')??$r->header('X-Late-Signature');$expected=hash_hmac('sha256',$raw,$secret);if(!$sig||!hash_equals($expected,$sig))return response()->json(['error'=>'Invalid signature'],401);
$eventId=$r->header('X-Zernio-Event-Id')??$r->header('X-Late-Event-Id')??($payload['id']??null);$eventType=$r->header('X-Zernio-Event')??($payload['event']??'unknown');if(!$eventId)return response()->json(['error'=>'Missing event id'],400);$event=WebhookEvent::firstOrCreate(['provider'=>'zernio','event_id'=>$eventId],['event_type'=>$eventType,'payload'=>$payload,'status'=>'pending']);if(!$event->wasRecentlyCreated)return response()->json(['ok'=>true,'duplicate'=>true]);ProcessZernioWebhook::dispatch($event->id);return response()->json(['ok'=>true],202);}}
