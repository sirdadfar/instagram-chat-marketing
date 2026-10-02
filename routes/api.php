<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
Route::post('/webhooks/inbound',[WebhookController::class,'zernio']);
Route::post('/webhooks/zernio',[WebhookController::class,'zernio']);
