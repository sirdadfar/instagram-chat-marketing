<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('body');
            $t->string('language', 10)->default('fa');
            $t->string('category', 40)->default('general');
            $t->json('variables')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['category', 'active']);
        });

        Schema::create('media_library', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type', 30);
            $t->string('url', 2048);
            $t->string('mime_type', 120)->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->string('alt_text')->nullable();
            $t->json('metadata')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['type', 'active']);
        });

        Schema::create('automation_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('timezone', 80)->default('Asia/Tehran');
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->index(['automation_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_schedules');
        Schema::dropIfExists('media_library');
        Schema::dropIfExists('message_templates');
    }
};
