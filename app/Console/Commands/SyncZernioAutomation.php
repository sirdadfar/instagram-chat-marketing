<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Automation;
use App\Services\Automation\ZernioAutomationSync;
class SyncZernioAutomation extends Command {
 protected $signature='zernio:sync-automation {automationId}';
 protected $description='Sync one automation to Zernio native Comment Automation';
 public function handle(ZernioAutomationSync $sync):int {
  $a=Automation::with(['keywords','actions','account'])->find($this->argument('automationId'));
  if(!$a){$this->error('Automation not found.');return self::FAILURE;}
  try{$r=$sync->sync($a);$remote=data_get($r,'automation.id')??data_get($r,'data.id')??data_get($r,'id');if($remote)$a->update(['zernio_automation_id'=>$remote]);$this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return self::SUCCESS;}catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
 }
}
