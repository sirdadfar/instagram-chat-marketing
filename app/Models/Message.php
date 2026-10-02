<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Message extends Model { protected $fillable=['conversation_id','external_message_id','direction','type','text','status','source','attachments','metadata','sent_at']; protected $casts=['attachments'=>'array','metadata'=>'array','sent_at'=>'datetime']; }
