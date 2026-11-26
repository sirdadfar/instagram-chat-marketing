<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Every automation now runs in the panel's local engine. The native
        // Zernio copy caused double replies and paused automations whenever the
        // remote sync failed.
        DB::table('automations')
            ->where(function ($query): void {
                $query->whereNull('execution_mode')->orWhere('execution_mode', '!=', 'local');
            })
            ->update(['execution_mode' => 'local']);

        // A failure marker is only written when the native sync failed, so a
        // paused row that carries it was paused by the sync, not by the user.
        // Clear the banner and re-activate those rows.
        DB::table('automations')
            ->select(['id', 'status', 'settings'])
            ->whereNotNull('settings')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $settings = json_decode((string) $row->settings, true);

                    if (! is_array($settings)) {
                        continue;
                    }

                    $failed = array_key_exists('nativeSyncError', $settings)
                        || array_key_exists('nativeSyncFailedAt', $settings);

                    if (! $failed) {
                        continue;
                    }

                    unset($settings['nativeSyncError'], $settings['nativeSyncFailedAt']);
                    $settings['nativeSyncRetiredAt'] = now()->toIso8601String();

                    $update = [
                        'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ];

                    if ($row->status === 'paused') {
                        $update['status'] = 'active';
                    }

                    DB::table('automations')->where('id', $row->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        // One-way change: the native sync path is no longer used.
    }
};
