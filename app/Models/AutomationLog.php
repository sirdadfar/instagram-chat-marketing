<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AutomationLog extends Model { protected $fillable=['automation_id','event_type','event_id','status','input','matched_keywords','conditions','actions_result','error']; protected $casts=['input'=>'array','matched_keywords'=>'array','conditions'=>'array','actions_result'=>'array']; public function automation():BelongsTo{return $this->belongsTo(Automation::class);}}
