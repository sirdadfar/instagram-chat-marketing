<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username')->nullable()->unique()->after('id');
            $t->string('email')->nullable()->change();
        });

        // Deterministic backfill: email local-part when available, otherwise user{id}.
        DB::table('users')->whereNull('username')->orderBy('id')->chunkById(100, function ($users): void {
            $taken = DB::table('users')->whereNotNull('username')->pluck('username')->flip();
            foreach ($users as $user) {
                $base = $user->email !== null ? explode('@', $user->email)[0] : 'user';
                $base = mb_strtolower((string) preg_replace('/[^a-z0-9_.-]/i', '', $base));
                if ($base === '') {
                    $base = 'user';
                }
                $username = $base;
                $i = 1;
                while ($taken->has($username)) {
                    $username = $base . $i++;
                }
                $taken->put($username, true);
                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('username');
        });
    }
};
