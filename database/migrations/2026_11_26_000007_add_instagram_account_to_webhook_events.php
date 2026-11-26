<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            // Which tenant page this delivery belongs to. Null for events whose
            // payload has no account block (tests, unsupported event families).
            $table->foreignId('instagram_account_id')
                ->nullable()
                ->after('event_type')
                ->constrained('instagram_accounts')
                ->nullOnDelete();
            $table->index('instagram_account_id');
        });

        // Existing deliveries were stored before the column existed. Attribute
        // the ones whose payload names a known page, so tenants immediately see
        // their recent events instead of an empty page.
        if (Schema::hasTable('instagram_accounts')) {
            try {
                DB::table('instagram_accounts')
                    ->select('id', 'zernio_account_id')
                    ->whereNotNull('zernio_account_id')
                    ->where('zernio_account_id', '<>', '')
                    ->orderBy('id')
                    ->chunk(100, function ($accounts): void {
                        foreach ($accounts as $account) {
                            DB::table('webhook_events')
                                ->whereNull('instagram_account_id')
                                ->where(function ($query) use ($account): void {
                                    $query->where('payload->account->accountId', $account->zernio_account_id)
                                        ->orWhere('payload->account->id', $account->zernio_account_id);
                                })
                                ->update(['instagram_account_id' => $account->id]);
                        }
                    });
            } catch (\Throwable $e) {
                // JSON support differs across database engines. The backfill is
                // best-effort; new deliveries are attributed by the controller.
                report($e);
            }
        }
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('instagram_account_id');
        });
    }
};
