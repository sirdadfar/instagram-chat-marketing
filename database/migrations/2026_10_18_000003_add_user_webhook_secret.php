<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('zernio_webhook_secret_enc')->nullable()->after('zernio_api_key_enc'); // per-user inbound webhook signing secret (encrypted at rest)
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('zernio_webhook_secret_enc'));
    }
};
