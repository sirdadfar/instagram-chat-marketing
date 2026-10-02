<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationUserCooldown extends Model { protected $fillable=['automation_id','user_key','last_executed_at','daily_count','count_date']; protected $casts=['last_executed_at'=>'datetime','count_date'=>'date']; }
