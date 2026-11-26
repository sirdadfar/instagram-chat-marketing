<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Services\Automation\ZernioAutomationSync;
use Illuminate\Console\Command;

class SyncZernioAutomation extends Command
{
    protected $signature = 'zernio:sync-automation {automationId}';

    protected $description = 'Retire the native Zernio copy of one automation; the panel runs it locally';

    public function handle(ZernioAutomationSync $sync): int
    {
        $automation = Automation::with('account')->find($this->argument('automationId'));

        if (! $automation) {
            $this->error('Automation not found.');

            return self::FAILURE;
        }

        if ($automation->zernio_automation_id) {
            try {
                $sync->disable($automation);
            } catch (\Throwable $e) {
                report($e);
                $this->error('The native copy could not be disabled: '.$e->getMessage());

                return self::FAILURE;
            }

            $automation->forceFill(['zernio_automation_id' => null])->save();
            $this->info('Native Zernio copy disabled.');
        }

        $settings = is_array($automation->settings) ? $automation->settings : [];
        unset($settings['nativeSyncError'], $settings['nativeSyncFailedAt']);
        $settings['nativeSyncRetiredAt'] = now()->toIso8601String();

        $automation->forceFill([
            'execution_mode' => 'local',
            'settings' => $settings,
        ])->save();

        $this->info('Automation now runs in the panel\'s local engine.');

        return self::SUCCESS;
    }
}
