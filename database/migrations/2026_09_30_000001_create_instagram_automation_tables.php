<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('instagram_accounts', function(Blueprint $t){$t->id();$t->string('zernio_account_id')->unique();$t->string('username')->nullable();$t->string('name')->nullable();$t->string('status')->default('active');$t->json('metadata')->nullable();$t->timestamps();});
  Schema::create('automations', function(Blueprint $t){$t->id();$t->foreignId('instagram_account_id')->constrained()->cascadeOnDelete();$t->string('name');$t->text('description')->nullable();$t->string('trigger',30);$t->string('status',20)->default('draft');$t->string('match_mode',10)->default('any');$t->string('target_type',20)->nullable();$t->string('target_id')->nullable();$t->unsignedInteger('cooldown_seconds')->default(0);$t->unsignedInteger('max_per_user_day')->nullable();$t->boolean('sync_to_zernio')->default(true);$t->string('zernio_automation_id')->nullable()->index();$t->json('audience')->nullable();$t->json('settings')->nullable();$t->timestamps();$t->index(['instagram_account_id','status']);});
  Schema::create('automation_keywords', function(Blueprint $t){$t->id();$t->foreignId('automation_id')->constrained()->cascadeOnDelete();$t->string('keyword');$t->string('match_type',20)->default('contains');$t->string('language',10)->nullable();$t->boolean('is_excluded')->default(false);$t->timestamps();});
  Schema::create('automation_actions', function(Blueprint $t){$t->id();$t->foreignId('automation_id')->constrained()->cascadeOnDelete();$t->string('action',40);$t->unsignedInteger('sort_order')->default(0);$t->json('config')->nullable();$t->timestamps();$t->index(['automation_id','sort_order']);});
  Schema::create('webhook_events', function(Blueprint $t){$t->id();$t->string('provider');$t->string('event_id');$t->string('event_type');$t->json('payload');$t->string('status')->default('pending');$t->text('error')->nullable();$t->timestamp('processed_at')->nullable();$t->timestamps();$t->unique(['provider','event_id']);});
  Schema::create('automation_logs', function(Blueprint $t){$t->id();$t->foreignId('automation_id')->constrained()->cascadeOnDelete();$t->string('event_type')->nullable();$t->string('event_id')->nullable();$t->string('status',30);$t->json('input')->nullable();$t->json('matched_keywords')->nullable();$t->json('conditions')->nullable();$t->json('actions_result')->nullable();$t->text('error')->nullable();$t->timestamps();$t->index(['automation_id','created_at']);});
  Schema::create('automation_user_cooldowns', function(Blueprint $t){$t->id();$t->foreignId('automation_id')->constrained()->cascadeOnDelete();$t->string('user_key');$t->timestamp('last_executed_at')->nullable();$t->unsignedInteger('daily_count')->default(0);$t->date('count_date')->nullable();$t->timestamps();$t->unique(['automation_id','user_key']);});
 }
 public function down(): void { foreach(['automation_user_cooldowns','automation_logs','webhook_events','automation_actions','automation_keywords','automations','instagram_accounts'] as $t) Schema::dropIfExists($t); }
};
