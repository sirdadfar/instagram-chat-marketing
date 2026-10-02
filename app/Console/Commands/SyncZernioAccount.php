<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\InstagramAccount;
use App\Services\Zernio\ZernioClient;
class SyncZernioAccount extends Command {
 protected $signature='zernio:account {accountId?} {--username=}';
 protected $description='Register or discover the connected Zernio Instagram account';
 public function handle(ZernioClient $z):int {
  $id=$this->argument('accountId')?:env('ZERNIO_ACCOUNT_ID');
  if($id){InstagramAccount::updateOrCreate(['zernio_account_id'=>$id],['username'=>$this->option('username')?:env('INSTAGRAM_USERNAME'),'status'=>'active']);$this->info('Instagram account registered: '.$id);return self::SUCCESS;}
  try{$r=$z->listAccounts(['platform'=>'instagram']);$items=data_get($r,'accounts',data_get($r,'data',[]));if(!is_array($items)||!count($items)){$this->warn('No Instagram account found.');return self::FAILURE;}foreach($items as $x){$accountId=$x['_id']??$x['id']??$x['accountId']??null;if(!$accountId)continue;InstagramAccount::updateOrCreate(['zernio_account_id'=>$accountId],['username'=>$x['username']??data_get($x,'metadata.username'),'name'=>$x['name']??null,'status'=>'active','metadata'=>$x]);$this->line('Synced: '.$accountId.' '.($x['username']??''));}return self::SUCCESS;}catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
 }
}
