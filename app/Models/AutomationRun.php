<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationRun extends Model { protected $fillable=['automation_id','event_id','status','context','result','error','started_at','finished_at']; protected $casts=['context'=>'array','result'=>'array','started_at'=>'datetime','finished_at'=>'datetime']; }
