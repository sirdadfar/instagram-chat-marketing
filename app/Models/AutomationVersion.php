<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationVersion extends Model { protected $fillable=['automation_id','version','label','snapshot','created_by']; protected $casts=['snapshot'=>'array']; }
