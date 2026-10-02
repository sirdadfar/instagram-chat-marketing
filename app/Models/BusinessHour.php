<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessHour extends Model { protected $fillable=['day_of_week','opens_at','closes_at','enabled']; protected $casts=['enabled'=>'boolean']; }
