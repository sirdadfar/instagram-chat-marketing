<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('automations',function(Blueprint $t){$t->string('execution_mode',20)->default('local')->after('status');$t->unsignedInteger('priority')->default(100)->after('execution_mode');$t->boolean('global_enabled')->default(true)->after('priority');$t->boolean('business_hours_only')->default(false)->after('global_enabled');$t->boolean('handoff_enabled')->default(false)->after('business_hours_only');}); }
 public function down(): void { Schema::table('automations',function(Blueprint $t){$t->dropColumn(['execution_mode','priority','global_enabled','business_hours_only','handoff_enabled']);}); }
};
