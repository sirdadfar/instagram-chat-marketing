<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->json('triggers')->nullable()->after('trigger');
        });

        // Every existing automation keeps its current trigger as a one-item set.
        DB::table('automations')
            ->select(['id', 'trigger'])
            ->orderBy('id')
            ->chunkById(200, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('automations')
                        ->where('id', $row->id)
                        ->update(['triggers' => json_encode([(string) $row->trigger], JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->dropColumn('triggers');
        });
    }
};
