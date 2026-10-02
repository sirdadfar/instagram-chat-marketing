<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationNode extends Model { protected $fillable=['automation_id','node_key','type','label','position_x','position_y','config']; protected $casts=['config'=>'array']; }
