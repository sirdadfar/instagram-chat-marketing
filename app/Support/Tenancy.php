<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\InstagramAccount;
use InvalidArgumentException;

/**
 * Multi-tenant data isolation.
 *
 * Every page (InstagramAccount) belongs to a user; all tenant data
 * (automations, inbox, contacts, comments, media...) hangs off pages.
 * Admins see everything; jobs/console run in system context (no session)
 * and therefore also see everything.
 */
final class Tenancy
{
    /**
     * Account query visible in the current context.
     */
    public static function accounts(): \Illuminate\Database\Eloquent\Builder
    {
        return self::scope(InstagramAccount::query());
    }

    /** Scope a query by a user_id column. */
    public static function scope(\Illuminate\Database\Eloquent\Builder $query, string $column = 'user_id'): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        if ($user && ! $user->isAdmin()) {
            $query->where($column, $user->id);
        }
        return $query;
    }

    /** Ids of pages visible in the current context. */
    public static function visibleAccountIds(): array
    {
        return self::accounts()->pluck('id')->all();
    }

    /** Ids of automations visible in the current context. */
    public static function visibleAutomationIds(): array
    {
        return \App\Models\Automation::query()
            ->whereIn('instagram_account_id', self::visibleAccountIds())
            ->pluck('id')
            ->all();
    }

    /** 404 unless the page belongs to the current user (admins pass). */
    public static function authorizeAccount(?InstagramAccount $account): InstagramAccount
    {
        if ($account === null) {
            abort(404);
        }

        $user = auth()->user();
        if ($user && ! $user->isAdmin() && $account->user_id !== $user->id) {
            abort(404);
        }

        return $account;
    }
}
