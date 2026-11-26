<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Services\Automation\ZernioAutomationSync;
use Illuminate\Console\Command;

class RetireNativeZernioAutomations extends Command
{
    protected $signature = 'zernio:retire-native {--dry-run}';

    protected $description = 'Move automations to the local engine and disable legacy Zernio native copies';

    public function handle(ZernioAutomationSync $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $failed = 0;

        Automation::with('account')->chunkById(100, function ($automations) use ($sync, $dryRun, &$failed): void {
            foreach ($automations as $automation) {
                $settings = is_array($automation->settings) ? $automation->settings : [];
                $settingsChanged = false;

                if (array_key_exists('nativeSyncError', $settings) || array_key_exists('nativeSyncFailedAt', $settings)) {
                    unset($settings['nativeSyncError'], $settings['nativeSyncFailedAt']);
                    $settings['nativeSyncRetiredAt'] = now()->toIso8601String();
                    $settingsChanged = true;
                }

                $hasRemote = filled($automation->zernio_automation_id);
                $needsMode = $automation->execution_mode !== 'local';

                if (! $settingsChanged && ! $hasRemote && ! $needsMode) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("would retire #{$automation->id} {$automation->name}");

                    continue;
                }

                if ($hasRemote) {
                    try {
                        // The service pins the page owner's key before calling Zernio.
                        $sync->disable($automation);
                        $automation->forceFill(['zernio_automation_id' => null])->save();
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                        $this->error("could not disable the native copy of #{$automation->id} {$automation->name}: {$e->getMessage()}");

                        // Keep the row on the native path when the remote copy
                        // may still be active, otherwise it answers twice.
                        if ($automation->execution_mode !== 'zernio') {
                            $automation->forceFill(['execution_mode' => 'zernio'])->save();
                        }

                        continue;
                    }
                }

                $automation->forceFill([
                    'execution_mode' => 'local',
                    'settings' => $settings,
                ])->save();

                $this->info("retired #{$automation->id} {$automation->name}".($hasRemote ? ' (native copy disabled)' : ''));
            }
        });

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
