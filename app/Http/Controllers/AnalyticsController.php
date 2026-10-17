<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\Conversation;
use App\Models\InstagramAccount;
use App\Models\WebhookEvent;
use App\Services\Zernio\ZernioClient;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, ZernioClient $client)
    {
        $days = collect(range(6, 0))->map(fn ($n) => now()->subDays($n)->startOfDay());
        $labels = $days->map(fn ($d) => $d->format('m/d'));
        $runs = $days->map(fn ($d) => AutomationRun::whereIn('automation_id', Tenancy::visibleAutomationIds())->whereBetween('created_at', [$d, $d->copy()->endOfDay()])->count());
        $failed = $days->map(fn ($d) => AutomationRun::whereIn('automation_id', Tenancy::visibleAutomationIds())->where('status', 'failed')->whereBetween('created_at', [$d, $d->copy()->endOfDay()])->count());

        $accounts = Tenancy::accounts()->where('status', 'active')->orderBy('username')->get();
        $selectedAccount = $accounts->firstWhere('id', $request->integer('account_id')) ?? $accounts->first();
        $account = $selectedAccount;

        $insights = null;
        $followers = null;
        $demographics = null;
        $apiNotice = null;

        if ($account) {
            try {
                $insights = $client->getInstagramAccountInsights($account->zernio_account_id, [
                    'metrics' => 'reach,views,accounts_engaged,total_interactions,follows_and_unfollows',
                    'metricType' => 'total_value',
                ]);
            } catch (\Throwable $e) {
                report($e);
                $apiNotice = 'آمار حساب فعلاً در دسترس نیست.';
            }

            try {
                $followersResponse = $client->getFollowerHistory($account->zernio_account_id, [
                    'metrics' => 'follower_count,followers_gained,followers_lost',
                    'metricType' => 'time_series',
                ]);
                $followers = data_get($followersResponse, 'metrics.follower_count.values', []);
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $demographics = $client->getDemographics($account->zernio_account_id, [
                    'metric' => 'follower_demographics',
                    'breakdown' => 'age,city,country,gender',
                    'timeframe' => 'this_month',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('analytics.index', [
            'labels' => $labels,
            'runs' => $runs,
            'failed' => $failed,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'account' => $account,
            'insights' => $insights,
            'followers' => $followers,
            'demographics' => $demographics,
            'apiNotice' => $apiNotice,
            'totals' => [
                'runs' => AutomationRun::whereIn('automation_id', Tenancy::visibleAutomationIds())->count(),
                'success' => AutomationRun::whereIn('automation_id', Tenancy::visibleAutomationIds())->where('status', 'completed')->count(),
                'failed' => AutomationRun::whereIn('automation_id', Tenancy::visibleAutomationIds())->where('status', 'failed')->count(),
                'human' => Conversation::whereIn('instagram_account_id', Tenancy::visibleAccountIds())->where('needs_human', true)->count(),
                'events' => auth()->user()->isAdmin() ? WebhookEvent::count() : 0,
                'errors' => AutomationLog::whereIn('automation_id', Tenancy::visibleAutomationIds())->where('status', 'failed')->count(),
            ],
        ]);
    }
}
