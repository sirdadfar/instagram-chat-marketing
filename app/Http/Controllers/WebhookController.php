<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessZernioWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class WebhookController extends Controller {public function zernio(Request $r):JsonResponse {$secret=config('zernio.webhook_secret');$raw=$r->getContent();if(!$secret)return response()->json(['error'=>'Webhook secret is not configured'],503);$sig=$r->header('X-Zernio-Signature')??$r->header('X-Late-Signature');$expected=hash_hmac('sha256',$raw,$secret);if(!$sig||!hash_equals($expected,$sig))return response()->json(['error'=>'Invalid signature'],401);try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){return response()->json(['error'=>'Invalid JSON'],400);}$eventId=$r->header('X-Zernio-Event-Id')??$r->header('X-Late-Event-Id')??($payload['id']??null);$eventType=$r->header('X-Zernio-Event')??($payload['event']??'unknown');if(!$eventId)return response()->json(['error'=>'Missing event id'],400);$event=WebhookEvent::firstOrCreate(['provider'=>'zernio','event_id'=>$eventId],['event_type'=>$eventType,'payload'=>$payload,'status'=>'pending']);if(!$event->wasRecentlyCreated)return response()->json(['ok'=>true,'duplicate'=>true]);ProcessZernioWebhook::dispatch($event->id);return response()->json(['ok'=>true],202);}}
