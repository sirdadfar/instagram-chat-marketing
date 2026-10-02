<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { protected $fillable=['action','entity_type','entity_id','ip_address','before','after']; protected $casts=['before'=>'array','after'=>'array']; }
