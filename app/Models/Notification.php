<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model { protected $fillable=['type','title','body','read','data']; protected $casts=['read'=>'boolean','data'=>'array']; }
