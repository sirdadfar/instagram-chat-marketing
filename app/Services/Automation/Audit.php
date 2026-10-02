<?php
namespace App\Services\Automation;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class Audit { public function record(string $action,?string $type=null,?int $id=null,?array $before=null,?array $after=null):void { AuditLog::create(['action'=>$action,'entity_type'=>$type,'entity_id'=>$id,'ip_address'=>app()->bound(Request::class)?request()->ip():null,'before'=>$before,'after'=>$after]); } }
