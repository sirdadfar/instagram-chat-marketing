<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationEdge extends Model { protected $fillable=['automation_id','source_key','target_key','condition','config']; protected $casts=['config'=>'array']; }
