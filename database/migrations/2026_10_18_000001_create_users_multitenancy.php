<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('is_admin')->default(false);
            $t->string('status', 20)->default('active'); // active | suspended
            $t->text('zernio_api_key_enc')->nullable(); // per-user service key (encrypted at rest)
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // Tenant ownership: every Instagram page belongs to a user.
        Schema::table('instagram_accounts', function (Blueprint $t) {
            $t->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
            $t->index('user_id');
        });

        Schema::table('media_library', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('message_templates', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instagram_accounts', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('media_library', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('message_templates', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::dropIfExists('users');
    }
};
