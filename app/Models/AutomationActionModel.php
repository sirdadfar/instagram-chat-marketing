<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationActionModel extends Model { protected $table='automation_actions'; protected $fillable=['automation_id','action','sort_order','config']; protected $casts=['config'=>'array']; }
