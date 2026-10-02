<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AppSetting extends Model { protected $fillable=['key','value']; public static function getValue(string $key,mixed $default=null):mixed { $v=static::where('key',$key)->value('value'); if($v===null)return $default; $decoded=json_decode($v,true); return json_last_error()===JSON_ERROR_NONE?$decoded:$v; } public static function setValue(string $key,mixed $value):void {static::updateOrCreate(['key'=>$key],['value'=>is_string($value)?$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);} }
