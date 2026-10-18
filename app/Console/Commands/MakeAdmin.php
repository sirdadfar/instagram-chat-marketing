<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Promote an existing user to admin (idempotent).
 * The user is matched by username or email.
 */
class MakeAdmin extends Command
{
    protected $signature = 'panel:make-admin {user : Username or email of the user to promote}';

    protected $description = 'Grant admin access to an existing user (match by username or email)';

    public function handle(): int
    {
        $needle = mb_strtolower(trim((string) $this->argument('user')));

        $user = User::query()
            ->where('username', $needle)
            ->orWhere('email', $needle)
            ->first();

        if ($user === null) {
            $this->error("User '$needle' not found.");
            $this->table(['id', 'username', 'email', 'is_admin'], User::query()
                ->get(['id', 'username', 'email', 'is_admin'])
                ->map(fn (User $u) => [$u->id, $u->username, $u->email, $u->is_admin ? 'yes' : 'no'])
                ->all());

            return Command::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("{$user->username} is already an admin.");

            return Command::SUCCESS;
        }

        $user->update(['is_admin' => true, 'status' => 'active']);
        $this->info("{$user->username} is now an admin.");

        return Command::SUCCESS;
    }
}
