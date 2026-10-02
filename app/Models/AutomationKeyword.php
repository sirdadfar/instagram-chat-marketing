<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomationKeyword extends Model { protected $fillable=['automation_id','keyword','match_type','language','is_excluded']; protected $casts=['is_excluded'=>'boolean']; }
