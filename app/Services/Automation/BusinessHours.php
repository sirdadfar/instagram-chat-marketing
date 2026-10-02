<?php
namespace App\Services\Automation;
use App\Models\AppSetting;
use Carbon\Carbon;
class BusinessHours { public function allowed():bool { if(!AppSetting::getValue('business_hours_enabled',false))return true; $rules=AppSetting::getValue('business_hours',[]); $day=now()->dayOfWeek; $r=$rules[$day]??null; if(!$r || empty($r['enabled']))return false; $time=now()->format('H:i'); return $time >= ($r['from']??'00:00') && $time <= ($r['to']??'23:59'); } }
