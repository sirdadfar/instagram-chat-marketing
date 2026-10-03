<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Conversation;
use App\Models\InstagramAccount;
use App\Models\Notification;
use App\Models\WebhookEvent;
use App\Services\Automation\Audit;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\ZernioAutomationSync;
use App\Services\Zernio\ZernioClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class PanelController extends Controller
{
    public function dashboard()
    {
        return view('dashboard.index', [
            'accounts' => InstagramAccount::all(),
            'automations' => Automation::withCount('logs')->orderBy('priority')->latest()->take(8)->get(),
            'runs' => \App\Models\AutomationRun::latest()->take(10)->get(),
            'conversations' => Conversation::with('contact')->latest('last_message_at')->take(6)->get(),
            'events' => WebhookEvent::latest()->take(6)->get(),
            'stats' => [
                'automations' => Automation::count(),
                'active' => Automation::where('status', 'active')->count(),
                'runs' => \App\Models\AutomationRun::count(),
                'failed' => AutomationLog::where('status', 'failed')->count(),
                'human' => Conversation::where('needs_human', true)->count(),
            ],
        ]);
    }

    public function automations()
    {
        return view('automations.index', [
            'automations' => Automation::with(['account', 'actions'])
                ->withCount(['logs', 'runs'])
                ->orderBy('priority')
                ->paginate(20),
        ]);
    }

    public function create(ZernioClient $zernio)
    {
        $accounts = InstagramAccount::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        // اگر حسابی هنوز در دیتابیس ثبت نشده، از Zernio به‌صورت خودکار همگام‌سازی کن.
        if ($accounts->where('status', 'active')->isEmpty() && config('zernio.api_key')) {
            try {
                $response = $zernio->listAccounts(['platform' => 'instagram']);
                $items = data_get($response, 'accounts');
                if (!is_array($items)) {
                    $items = data_get($response, 'data.accounts', data_get($response, 'data', []));
                }

                foreach ((array) $items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $accountId = $item['id'] ?? $item['accountId'] ?? $item['_id'] ?? null;
                    if (!$accountId) {
                        continue;
                    }

                    $providerStatus = strtolower((string) ($item['status'] ?? $item['connectionStatus'] ?? $item['state'] ?? 'active'));
                    $activeProviderStatuses = ['active', 'connected', 'authenticated', 'ready', 'online', 'enabled'];

                    InstagramAccount::updateOrCreate(
                        ['zernio_account_id' => (string) $accountId],
                        [
                            'username' => $item['username'] ?? data_get($item, 'metadata.username'),
                            'name' => $item['name'] ?? $item['displayName'] ?? data_get($item, 'metadata.name'),
                            'status' => in_array($providerStatus, $activeProviderStatuses, true) ? 'active' : 'inactive',
                            'metadata' => $item,
                        ]
                    );
                }

                $accounts = InstagramAccount::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderByDesc('created_at')
                    ->get();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $selectedAccount = $accounts->firstWhere('status', 'active');

        return view('automations.create', [
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
        ]);
    }

    public function uploadContentMedia(Request $request, ZernioClient $zernio)
    {
        $data = $request->validate([
            'file' => [
                'required',
                File::types(['jpg','jpeg','png','mp4','mov']),
                'max:307200',
            ],
        ]);

        try {
            $response = $zernio->uploadMediaDirect($data['file']);
            $url = data_get($response, 'url', data_get($response, 'data.url'));
            if (!is_string($url) || $url === '') {
                return response()->json(['ok'=>false,'message'=>'آدرس عمومی رسانه دریافت نشد.'],422);
            }
            return response()->json([
                'ok'=>true,
                'url'=>$url,
                'filename'=>data_get($response,'filename',$data['file']->getClientOriginalName()),
                'contentType'=>data_get($response,'contentType',$data['file']->getMimeType()),
                'size'=>data_get($response,'size',$data['file']->getSize()),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $status = $e instanceof \Illuminate\Http\Client\RequestException && $e->response ? $e->response->status() : 422;
            $payload = $e instanceof \Illuminate\Http\Client\RequestException && $e->response ? $e->response->json() : [];
            return response()->json([
                'ok'=>false,
                'message'=>data_get($payload,'message',data_get($payload,'error','بارگذاری رسانه انجام نشد.')),
            ], in_array($status,[400,401,403,413,422,429],true) ? $status : 422);
        }
    }

    public function uploadMedia(Request $request, ZernioClient $zernio)
    {
        $data = $request->validate([
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'avi', 'webm', 'm4a', 'aac', 'wav']),
                'max:25600',
            ],
        ]);

        try {
            $response = $zernio->uploadMediaDirect($data['file']);
            $url = data_get($response, 'url', data_get($response, 'data.url'));

            if (! is_string($url) || $url === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'آدرس عمومی رسانه دریافت نشد.',
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'url' => $url,
                'filename' => data_get($response, 'filename', $data['file']->getClientOriginalName()),
                'contentType' => data_get($response, 'contentType', $data['file']->getMimeType()),
                'size' => data_get($response, 'size', $data['file']->getSize()),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $status = $e instanceof \Illuminate\Http\Client\RequestException && $e->response
                ? $e->response->status()
                : 422;

            $payload = $e instanceof \Illuminate\Http\Client\RequestException && $e->response
                ? $e->response->json()
                : [];

            return response()->json([
                'ok' => false,
                'message' => data_get($payload, 'message', data_get($payload, 'error', 'بارگذاری رسانه انجام نشد.')),
            ], in_array($status, [400, 401, 403, 413, 422, 429], true) ? $status : 422);
        }
    }

    public function media(Request $request, ZernioClient $zernio)
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'type' => ['required', 'in:posts,stories'],
            'after' => ['nullable', 'string', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $account = InstagramAccount::query()
            ->whereKey($data['account_id'])
            ->where('status', 'active')
            ->first();

        if (!$account) {
            return response()->json([
                'ok' => false,
                'message' => 'حساب انتخاب‌شده فعال نیست. ابتدا اتصال اینستاگرام را بررسی و حساب را دوباره همگام‌سازی کنید.',
                'diagnostics' => ['kind' => 'local_account_inactive'],
            ], 422);
        }

        $query = [];
        if (!empty($data['after'])) {
            // Stories use cursor-style pagination; keep the same value as returned by Zernio.
            $query['after'] = $data['after'];
        }

        try {
            if ($data['type'] === 'stories') {
                $response = $zernio->listInstagramStories($account->zernio_account_id, $query);
                $items = data_get($response, 'stories', data_get($response, 'data.stories', []));
                $source = 'zernio_instagram_stories';
            } else {
                try {
                    // Preferred route: platform API-backed recent media.
                    $response = $zernio->listInstagramPosts($account->zernio_account_id, $query);
                    $items = data_get($response, 'posts', data_get($response, 'data.posts', []));
                    $source = 'zernio_instagram_posts';
                } catch (\Throwable $primaryException) {
                    // If the stored Zernio account id is stale, refresh it from the authoritative account list.
                    try {
                        $listed = $zernio->listAccounts(['platform' => 'instagram']);
                        $remoteAccounts = data_get($listed, 'accounts', data_get($listed, 'data.accounts', data_get($listed, 'data', [])));
                        $remoteAccounts = is_array($remoteAccounts) ? $remoteAccounts : [];

                        $match = collect($remoteAccounts)->first(function ($row) use ($account) {
                            if (!is_array($row)) {
                                return false;
                            }

                            $remoteId = $row['_id'] ?? $row['id'] ?? $row['accountId'] ?? null;
                            $remoteUsername = strtolower(trim((string) ($row['username'] ?? data_get($row, 'metadata.username', ''))));
                            $localUsername = strtolower(trim((string) $account->username));

                            return ($remoteId && (string) $remoteId === (string) $account->zernio_account_id)
                                || ($remoteUsername !== '' && $localUsername !== '' && $remoteUsername === $localUsername);
                        });

                        if (is_array($match)) {
                            $remoteId = $match['_id'] ?? $match['id'] ?? $match['accountId'] ?? null;
                            if ($remoteId && (string) $remoteId !== (string) $account->zernio_account_id) {
                                $account->update([
                                    'zernio_account_id' => (string) $remoteId,
                                    'username' => $match['username'] ?? $account->username,
                                    'name' => $match['name'] ?? $match['displayName'] ?? $account->name,
                                    'status' => !empty($match['isActive']) || in_array(strtolower((string) ($match['status'] ?? 'active')), ['active', 'connected', 'ready', 'authenticated', 'online', 'enabled'], true)
                                        ? 'active'
                                        : $account->status,
                                    'metadata' => $match,
                                ]);
                            }
                        }
                    } catch (\Throwable $syncException) {
                        report($syncException);
                    }

                    try {
                        // Current Zernio also provides on-demand syncing for external platform posts.
                        $fallback = $zernio->listInstagramPostsViaSyncExternal($account->zernio_account_id);
                        $items = data_get($fallback, 'posts', data_get($fallback, 'data.posts', []));
                        $response = $fallback;
                        $source = 'zernio_sync_external';
                    } catch (\Throwable $fallbackException) {
                        return $this->mediaApiErrorResponse($primaryException, $zernio, $account, $fallbackException);
                    }
                }
            }

            $items = is_array($items) ? $items : [];

            $normalized = collect($items)
                ->filter(fn ($item) => is_array($item))
                ->map(static function (array $item): array {
                    $id = (string) ($item['id']
                        ?? $item['platformPostId']
                        ?? $item['platform_post_id']
                        ?? $item['_id']
                        ?? '');

                    $type = strtoupper((string) (
                        $item['mediaType']
                        ?? $item['media_type']
                        ?? data_get($item, 'mediaItems.0.type', 'IMAGE')
                    ));

                    $productType = strtoupper((string) ($item['mediaProductType'] ?? $item['media_product_type'] ?? ''));
                    if ($productType === 'REELS' && $type === 'VIDEO') {
                        $type = 'REEL';
                    }

                    $isVideo = in_array($type, ['VIDEO', 'REEL'], true);
                    $isCarousel = in_array($type, ['CAROUSEL_ALBUM', 'CAROUSEL'], true);
                    $thumbnail = $item['thumbnailUrl']
                        ?? $item['thumbnail_url']
                        ?? data_get($item, 'mediaItems.0.thumbnail')
                        ?? $item['mediaUrl']
                        ?? $item['media_url']
                        ?? null;
                    $mediaUrl = $item['mediaUrl']
                        ?? $item['media_url']
                        ?? data_get($item, 'mediaItems.0.url');

                    return [
                        'id' => $id,
                        'mediaType' => $type,
                        'label' => $isCarousel ? 'آلبوم' : ($isVideo ? 'ویدیو / ریلز' : 'تصویر'),
                        'thumbnailUrl' => $thumbnail,
                        'mediaUrl' => $mediaUrl,
                        'permalink' => $item['permalink'] ?? $item['platformPostUrl'] ?? $item['platform_post_url'] ?? null,
                        'caption' => (string) ($item['caption'] ?? $item['content'] ?? ''),
                        'timestamp' => $item['timestamp'] ?? $item['publishedAt'] ?? $item['published_at'] ?? null,
                        'mediaProductType' => $productType,
                    ];
                })
                ->filter(fn ($item) => $item['id'] !== '')
                ->unique('id')
                ->values();

            return response()->json([
                'ok' => true,
                'items' => $normalized,
                'source' => $source,
                'account' => [
                    'id' => $account->id,
                    'username' => $account->username,
                    'status' => $account->status,
                ],
                'paging' => [
                    'after' => data_get($response, 'paging.after', data_get($response, 'data.paging.after')),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->mediaApiErrorResponse($e, $zernio, $account);
        }
    }

    private function mediaApiErrorResponse(\Throwable $exception, ZernioClient $zernio, InstagramAccount $account, ?\Throwable $secondary = null)
    {
        $primary = $this->zernioExceptionDetails($exception);
        $secondaryDetails = $secondary ? $this->zernioExceptionDetails($secondary) : null;
        $health = null;

        try {
            $health = $zernio->getAccountHealth($account->zernio_account_id);
        } catch (\Throwable $healthException) {
            $health = null;
        }

        $healthStatus = data_get($health, 'status');
        $needsReconnect = data_get($health, 'tokenStatus.valid') === false
            || data_get($health, 'summary.needsReconnect') === true
            || data_get($health, 'needsReconnect') === true;

        $message = match (true) {
            $primary['status'] === 401 => 'کلید API زرنيو نامعتبر یا منقضی شده است. مقدار ZERNIO_API_KEY را بررسی کنید.',
            $primary['status'] === 403 && ($primary['code'] === 'reconnect_required' || $needsReconnect) => 'اتصال اینستاگرام این حساب نیاز به اتصال مجدد دارد. حساب را در Zernio دوباره متصل کنید و سپس همگام‌سازی را انجام دهید.',
            $primary['status'] === 403 => 'زرنيو دسترسی لازم برای خواندن رسانه‌های این حساب را ندارد. مجوزها و وضعیت اتصال حساب را بررسی کنید.',
            $primary['status'] === 404 => 'حساب یا رسانه در زرنيو پیدا نشد. شناسه اتصال حساب ممکن است قدیمی باشد؛ حساب را دوباره Sync کنید.',
            $primary['status'] === 429 => 'تعداد درخواست‌ها به حد مجاز زرنيو رسیده است. چند لحظه بعد دوباره تلاش کنید.',
            $primary['status'] === 422 => $primary['message'] ?: 'زرنيو درخواست دریافت رسانه را نپذیرفت. وضعیت حساب و مجوزهای اینستاگرام را بررسی کنید.',
            default => $primary['message'] ?: 'دریافت رسانه‌ها از زرنيو انجام نشد.',
        };

        return response()->json([
            'ok' => false,
            'message' => $message,
            'diagnostics' => [
                'httpStatus' => $primary['status'],
                'code' => $primary['code'],
                'account' => $account->username,
                'healthStatus' => $healthStatus,
                'needsReconnect' => $needsReconnect,
                'details' => $primary['message'],
                'fallbackDetails' => $secondaryDetails['message'] ?? null,
            ],
        ], 422);
    }

    private function zernioExceptionDetails(\Throwable $exception): array
    {
        $status = null;
        $body = [];

        if ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response) {
            $status = $exception->response->status();
            $json = $exception->response->json();
            $body = is_array($json) ? $json : [];
        }

        $code = data_get($body, 'code', data_get($body, 'error.code'));
        $message = data_get($body, 'error', data_get($body, 'message', data_get($body, 'details.message')));
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!$message) {
            $message = $exception->getMessage();
        }

        return [
            'status' => $status,
            'code' => is_string($code) ? $code : null,
            'message' => is_string($message) ? $message : null,
        ];
    }

    public function workflows(Request $request, ZernioClient $zernio)
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
        ]);

        $account = InstagramAccount::query()
            ->whereKey($data['account_id'])
            ->where('status', 'active')
            ->first();

        if (! $account) {
            return response()->json([
                'ok' => false,
                'message' => 'حساب انتخاب‌شده فعال نیست.',
            ], 422);
        }

        try {
            $response = $zernio->listWorkflows([
                'accountId' => $account->zernio_account_id,
                'status' => 'active',
                'limit' => 100,
            ]);

            $items = data_get($response, 'workflows', data_get($response, 'data.workflows', data_get($response, 'data', [])));
            $items = is_array($items) ? $items : [];

            $normalized = collect($items)
                ->filter(fn ($item) => is_array($item))
                ->filter(function (array $item) use ($account): bool {
                    $platform = strtolower((string) ($item['platform'] ?? data_get($item, 'metadata.platform', 'instagram')));
                    $itemAccount = (string) ($item['accountId'] ?? data_get($item, 'account_id', data_get($item, 'metadata.accountId', '')));
                    return $platform === 'instagram' && ($itemAccount === '' || $itemAccount === (string) $account->zernio_account_id);
                })
                ->map(function (array $item): array {
                    return [
                        'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['workflowId'] ?? ''),
                        'name' => (string) ($item['name'] ?? $item['title'] ?? data_get($item, 'metadata.name', 'مسیر بدون عنوان')),
                        'status' => strtolower((string) ($item['status'] ?? 'active')),
                    ];
                })
                ->filter(fn (array $item) => $item['id'] !== '')
                ->unique('id')
                ->values();

            return response()->json(['ok' => true, 'items' => $normalized]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'مسیرهای گفتگو در دسترس نیستند. می‌توانی فعلاً دکمه لینک‌دار بسازی.',
            ], 422);
        }
    }

    public function store(Request $r, Audit $audit)
    {
        $d = $r->validate([
            'instagram_account_id' => 'required|exists:instagram_accounts,id',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'trigger' => 'required|in:comment,story_reply,direct_message',
            'goal' => 'nullable|in:comment_dm,comment_public,dm_reply',
            'status' => 'required|in:draft,active,paused',
            'execution_mode' => 'required|in:local,zernio',
            'priority' => 'required|integer|min:1|max:9999',
            'target_type' => 'nullable|in:post,story',
            'target_id' => 'nullable|string|max:255',
            'match_mode' => 'required|in:any,all,contains,exact,word',
            'keywords' => 'nullable|string',
            'excluded_keywords' => 'nullable|string',
            'cooldown_seconds' => 'required|integer|min:0|max:86400',
            'max_per_user_day' => 'nullable|integer|min:1|max:10000',
            'business_hours_only' => 'nullable|boolean',
            'handoff_enabled' => 'nullable|boolean',
            'audience_follower_status' => 'required|in:any,follower,non_follower',
            'audience_unknown' => 'required|in:send,skip,verify',
            'min_follower_count' => 'nullable|integer|min:0',
            'public_reply' => 'nullable|string|max:2200',
            'private_reply' => 'nullable|string|max:10000',
            'dm_message' => 'nullable|string|max:10000',
            'dm_attachment_url' => 'nullable|url|max:2048',
            'dm_attachment_type' => 'nullable|in:image,video,audio,file',
            'dm_attachment_name' => 'nullable|string|max:255',
            'dm_sequence_json' => 'nullable|string|max:100000',
            'variations' => 'nullable|string',
            'hide_comment' => 'nullable|boolean',
            'buttons_json' => 'nullable|string',
            'add_tag' => 'nullable|string|max:80',
            'quick_replies_json' => 'nullable|string',
            'template_json' => 'nullable|string',
            'also_match_in_dms' => 'nullable|boolean',
            'dm_delay_seconds' => 'nullable|integer|min:0|max:86400',
            'comment_reply_delay_seconds' => 'nullable|integer|min:0|max:86400',
            'link_tracking' => 'nullable|boolean',
            'message_tag' => 'nullable|in:HUMAN_AGENT',
            'link_preview' => 'nullable|boolean',
            'follow_gate_enabled' => 'nullable|boolean',
            'follow_gate_message' => 'nullable|string|max:640',
            'follow_gate_button_label' => 'nullable|string|max:20',
            'follow_gate_not_following_message' => 'nullable|string|max:640',
        ]);

        $followGateEnabled = $d['trigger'] === 'comment'
            && ($d['goal'] ?? 'comment_dm') !== 'comment_public'
            && (bool) ($d['follow_gate_enabled'] ?? false)
            && trim((string) ($d['private_reply'] ?? '')) !== '';

        $a = Automation::create([
            'instagram_account_id' => $d['instagram_account_id'],
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'trigger' => $d['trigger'],
            'status' => $d['status'],
            'execution_mode' => in_array($d['trigger'], ['comment', 'story_reply'], true) ? 'zernio' : $d['execution_mode'],
            'priority' => $d['priority'],
            'target_type' => $d['target_type'] ?? null,
            'target_id' => $d['target_id'] ?? null,
            'match_mode' => in_array($d['match_mode'], ['any', 'all'], true) ? $d['match_mode'] : 'any',
            'cooldown_seconds' => $d['cooldown_seconds'],
            'max_per_user_day' => $d['max_per_user_day'] ?? null,
            'business_hours_only' => (bool) ($d['business_hours_only'] ?? false),
            'handoff_enabled' => (bool) ($d['handoff_enabled'] ?? false),
            'audience' => [
                'followerStatus' => $followGateEnabled ? 'follower' : $d['audience_follower_status'],
                'whenUnknown' => $followGateEnabled ? 'verify' : $d['audience_unknown'],
                'minFollowerCount' => $d['min_follower_count'] ?? null,
            ],
            'settings' => [
                'matchMode' => $d['match_mode'],
                'dmMessageVariations' => array_values(array_filter(
                    preg_split('/\r?\n/', (string) ($d['variations'] ?? ''))
                )),
                'alsoMatchInDms' => (bool) ($d['also_match_in_dms'] ?? false),
                'dmDelaySeconds' => (int) ($d['dm_delay_seconds'] ?? 0),
                'commentReplyDelaySeconds' => (int) ($d['comment_reply_delay_seconds'] ?? 0),
                'linkTracking' => (bool) ($d['link_tracking'] ?? false),
                'followGateEnabled' => $followGateEnabled,
                'followGate' => [
                    'message' => trim((string) ($d['follow_gate_message'] ?? 'لطفاً ابتدا صفحه را دنبال کنید و سپس روی «بررسی کردم» بزنید.')),
                    'buttonLabel' => trim((string) ($d['follow_gate_button_label'] ?? 'بررسی کردم')),
                    'notFollowingMessage' => trim((string) ($d['follow_gate_not_following_message'] ?? 'بعد از دنبال‌کردن صفحه، دوباره تأیید کنید.')),
                ],
            ],
        ]);

        $this->syncKeywords($a, $d['keywords'] ?? '', $d['excluded_keywords'] ?? '', $d['match_mode']);
        $this->syncActions($a, $d);

        $a->load(['keywords', 'actions', 'account']);
        $a->versions()->create([
            'version' => 1,
            'label' => 'نسخه اولیه',
            'snapshot' => $a->toArray(),
        ]);
        $audit->record('automation.created', Automation::class, $a->id, null, $a->toArray());

        $this->syncNativeAutomation($a);

        return redirect()
            ->route('automations.edit', $a)
            ->with('success', 'اتوماسیون با موفقیت ساخته و آماده اجرا شد.');
    }

    public function edit(Automation $automation)
    {
        $automation->load(['keywords', 'actions', 'nodes', 'edges', 'account']);

        return view('automations.edit', compact('automation'));
    }

    public function update(Request $r, Automation $automation, Audit $audit)
    {
        $d = $r->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,active,paused',
            'priority' => 'required|integer|min:1|max:9999',
            'execution_mode' => 'required|in:local,zernio',
            'cooldown_seconds' => 'required|integer|min:0|max:86400',
            'max_per_user_day' => 'nullable|integer|min:1|max:10000',
            'business_hours_only' => 'nullable|boolean',
            'handoff_enabled' => 'nullable|boolean',
            'match_mode' => 'nullable|in:any,all,contains,exact,word',
            'keywords' => 'nullable|string',
            'excluded_keywords' => 'nullable|string',
            'public_reply' => 'nullable|string|max:2200',
            'private_reply' => 'nullable|string|max:10000',
            'dm_message' => 'nullable|string|max:10000',
            'dm_attachment_url' => 'nullable|url|max:2048',
            'dm_attachment_type' => 'nullable|in:image,video,audio,file',
            'dm_attachment_name' => 'nullable|string|max:255',
            'dm_sequence_json' => 'nullable|string|max:100000',
            'hide_comment' => 'nullable|boolean',
            'add_tag' => 'nullable|string|max:80',
            'buttons_json' => 'nullable|string',
            'quick_replies_json' => 'nullable|string',
            'template_json' => 'nullable|string',
            'also_match_in_dms' => 'nullable|boolean',
            'dm_delay_seconds' => 'nullable|integer|min:0|max:86400',
            'comment_reply_delay_seconds' => 'nullable|integer|min:0|max:86400',
            'link_tracking' => 'nullable|boolean',
            'message_tag' => 'nullable|in:HUMAN_AGENT',
            'link_preview' => 'nullable|boolean',
            'goal' => 'nullable|in:comment_dm,comment_public,dm_reply',
            'follow_gate_enabled' => 'nullable|boolean',
            'follow_gate_message' => 'nullable|string|max:640',
            'follow_gate_button_label' => 'nullable|string|max:20',
            'follow_gate_not_following_message' => 'nullable|string|max:640',
            'audience_follower_status' => 'nullable|in:any,follower,non_follower',
            'audience_unknown' => 'nullable|in:send,skip,verify',
            'min_follower_count' => 'nullable|integer|min:0',
        ]);

        $before = $automation->toArray();
        $matchMode = $d['match_mode'] ?? ($automation->settings['matchMode'] ?? 'any');
        $triggerValue = $automation->trigger instanceof \BackedEnum ? $automation->trigger->value : (string) $automation->trigger;
        $goalValue = $d['goal'] ?? ($triggerValue === 'comment' ? 'comment_dm' : 'dm_reply');
        $followGateEnabled = $triggerValue === 'comment'
            && $goalValue !== 'comment_public'
            && (bool) ($d['follow_gate_enabled'] ?? false)
            && trim((string) ($d['private_reply'] ?? '')) !== '';

        $automation->update([
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'status' => $d['status'],
            'priority' => $d['priority'],
            'execution_mode' => in_array($triggerValue, ['comment', 'story_reply'], true) ? 'zernio' : $d['execution_mode'],
            'cooldown_seconds' => $d['cooldown_seconds'],
            'max_per_user_day' => $d['max_per_user_day'] ?? null,
            'business_hours_only' => (bool) ($d['business_hours_only'] ?? false),
            'handoff_enabled' => (bool) ($d['handoff_enabled'] ?? false),
            'match_mode' => in_array($matchMode, ['any', 'all'], true) ? $matchMode : 'any',
            'audience' => array_merge($automation->audience ?? [], [
                'followerStatus' => $followGateEnabled ? 'follower' : ($d['audience_follower_status'] ?? data_get($automation->audience, 'followerStatus', 'any')),
                'whenUnknown' => $followGateEnabled ? 'verify' : ($d['audience_unknown'] ?? data_get($automation->audience, 'whenUnknown', 'send')),
                'minFollowerCount' => $d['min_follower_count'] ?? data_get($automation->audience, 'minFollowerCount'),
            ]),
            'settings' => array_merge($automation->settings ?? [], [
                'matchMode' => $matchMode,
                'alsoMatchInDms' => (bool) ($d['also_match_in_dms'] ?? data_get($automation->settings, 'alsoMatchInDms', false)),
                'dmDelaySeconds' => (int) ($d['dm_delay_seconds'] ?? data_get($automation->settings, 'dmDelaySeconds', 0)),
                'commentReplyDelaySeconds' => (int) ($d['comment_reply_delay_seconds'] ?? data_get($automation->settings, 'commentReplyDelaySeconds', 0)),
                'linkTracking' => (bool) ($d['link_tracking'] ?? data_get($automation->settings, 'linkTracking', false)),
                'followGateEnabled' => $followGateEnabled,
                'followGate' => [
                    'message' => trim((string) ($d['follow_gate_message'] ?? data_get($automation->settings, 'followGate.message', 'لطفاً ابتدا صفحه را دنبال کنید و سپس روی «بررسی کردم» بزنید.'))),
                    'buttonLabel' => trim((string) ($d['follow_gate_button_label'] ?? data_get($automation->settings, 'followGate.buttonLabel', 'بررسی کردم'))),
                    'notFollowingMessage' => trim((string) ($d['follow_gate_not_following_message'] ?? data_get($automation->settings, 'followGate.notFollowingMessage', 'بعد از دنبال‌کردن صفحه، دوباره تأیید کنید.'))),
                ],
            ]),
        ]);

        $this->syncKeywords(
            $automation,
            $d['keywords'] ?? '',
            $d['excluded_keywords'] ?? '',
            $matchMode
        );
        $this->syncActions($automation, $d);

        $automation->load(['keywords', 'actions', 'account']);
        $next = (int) $automation->versions()->max('version') + 1;
        $automation->versions()->create([
            'version' => $next,
            'label' => 'ویرایش از پنل',
            'snapshot' => $automation->toArray(),
        ]);
        $audit->record(
            'automation.updated',
            Automation::class,
            $automation->id,
            $before,
            $automation->toArray()
        );

        $this->syncNativeAutomation($automation);

        return back()->with('success', 'تغییرات ذخیره شد و نسخه جدید اتوماسیون ثبت شد.');
    }

    public function toggle(Automation $automation, ZernioAutomationSync $nativeSync)
    {
        $previous = $automation->status;
        $automation->update([
            'status' => $automation->status === 'active' ? 'paused' : 'active',
        ]);

        $automation->refresh();
        $triggerValue = $automation->trigger instanceof \BackedEnum ? $automation->trigger->value : (string) $automation->trigger;
        if (in_array($triggerValue, ['comment', 'story_reply'], true)) {
            try {
                $automation->load(['keywords', 'actions', 'account']);
                $response = $nativeSync->sync($automation);
                $remoteId = data_get($response, 'id', data_get($response, '_id', data_get($response, 'commentAutomationId')));
                if ($remoteId && ! $automation->zernio_automation_id) {
                    $automation->update(['zernio_automation_id' => (string) $remoteId]);
                }
            } catch (\Throwable $e) {
                $automation->update(['status' => $previous]);
                report($e);
                return back()->withErrors(['automation' => 'وضعیت اتوماسیون ذخیره نشد. دسترسی حساب یا تنظیمات آن را بررسی کنید.']);
            }
        }

        return back()->with('success', $automation->status === 'active' ? 'اتوماسیون فعال شد.' : 'اتوماسیون متوقف شد.');
    }

    public function destroy(Automation $automation, Audit $audit)
    {
        $before = $automation->toArray();
        $id = $automation->id;
        $automation->delete();
        $audit->record('automation.deleted', Automation::class, $id, $before, null);

        return redirect()->route('automations.index')->with('success', 'اتوماسیون حذف شد.');
    }

    public function simulator(Request $r, Automation $automation, AutomationEngine $engine)
    {
        $ctx = $r->validate([
            'text' => 'nullable|string',
            'trigger' => 'required|string',
            'post_id' => 'nullable|string',
            'story_id' => 'nullable|string',
            'user_id' => 'nullable|string',
            'username' => 'nullable|string',
        ]);

        $result = $engine->evaluate($automation, $ctx['text'] ?? '', $ctx);

        return response()->json($result);
    }

    public function inbox(Request $r)
    {
        $q = Conversation::with([
            'contact',
            'messages' => fn ($query) => $query->latest()->limit(1),
        ])
            ->orderByDesc('needs_human')
            ->orderByDesc('last_message_at');

        if ($r->boolean('human')) {
            $q->where('needs_human', true);
        }

        return view('inbox.index', [
            'conversations' => $q->paginate(30),
        ]);
    }

    public function conversation(Conversation $conversation)
    {
        $conversation->load(['contact.tags', 'messages' => fn ($query) => $query->oldest()]);

        return view('inbox.show', compact('conversation'));
    }

    public function sendMessage(Request $request, Conversation $conversation, ZernioClient $zernio)
    {
        $conversation->load('instagramAccount');

        $data = $request->validate([
            'message' => 'nullable|string|max:10000',
            'compose_attachment_url' => 'nullable|url|max:2048',
            'compose_attachment_type' => 'nullable|in:image,video,audio,file',
            'compose_attachment_name' => 'nullable|string|max:255',
            'quick_replies_json' => 'nullable|string|max:12000',
            'buttons_json' => 'nullable|string|max:12000',
            'template_json' => 'nullable|string|max:30000',
            'message_tag' => 'nullable|in:HUMAN_AGENT',
            'link_preview' => 'nullable|boolean',
            'attachment' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'avi', 'webm', 'm4a', 'aac', 'wav']),
                'max:25600',
            ],
        ]);

        if (trim((string) ($data['message'] ?? '')) === '' && ! $request->hasFile('attachment') && empty($data['compose_attachment_url'])) {
            return back()->withErrors(['message' => 'متن یا یک فایل رسانه‌ای برای ارسال انتخاب کنید.']);
        }

        if (! $conversation->instagramAccount || $conversation->instagramAccount->status !== 'active') {
            return back()->withErrors(['message' => 'حساب اینستاگرام این گفتگو فعال نیست.']);
        }

        $attachmentUrl = null;
        $attachmentType = null;

        try {
            if (! empty($data['compose_attachment_url'])) {
                $attachmentUrl = $data['compose_attachment_url'];
                $attachmentType = $data['compose_attachment_type'] ?: 'file';
            } elseif ($request->hasFile('attachment')) {
                $uploaded = $zernio->uploadMediaDirect($data['attachment']);
                $attachmentUrl = data_get($uploaded, 'url', data_get($uploaded, 'data.url'));
                $attachmentType = match (strtolower((string) $data['attachment']->getMimeType())) {
                    'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif' => 'image',
                    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm' => 'video',
                    'audio/mp4', 'audio/aac', 'audio/wav', 'audio/x-m4a' => 'audio',
                    default => 'file',
                };

                if (! $attachmentUrl) {
                    throw new \RuntimeException('آدرس عمومی رسانه دریافت نشد.');
                }
            }

            $attachmentName = $data['compose_attachment_name'] ?? ($request->hasFile('attachment') ? $data['attachment']->getClientOriginalName() : null);

            $body = [];
            if (trim((string) ($data['message'] ?? '')) !== '') {
                $body['message'] = trim((string) $data['message']);
            }
            if ($attachmentUrl) {
                $body['attachmentUrl'] = $attachmentUrl;
                $body['attachmentType'] = $attachmentType;
            }

            $quickReplies = $this->decodeJsonList($data['quick_replies_json'] ?? null);
            $buttons = $this->decodeJsonList($data['buttons_json'] ?? null);
            $template = $this->decodeJsonValue($data['template_json'] ?? null);
            if ($quickReplies) {
                $body['quickReplies'] = array_values(array_slice($quickReplies, 0, 13));
            } elseif ($buttons) {
                $body['buttons'] = array_values(array_slice($buttons, 0, 3));
            }
            if (is_array($template) && $template !== []) {
                $body['template'] = $template;
            }
            if (!empty($data['message_tag'])) {
                $body['messageTag'] = $data['message_tag'];
                $body['messagingType'] = 'MESSAGE_TAG';
            }
            $body['linkPreview'] = $request->boolean('link_preview');

            $result = $zernio->sendMessage(
                $conversation->zernio_conversation_id,
                $conversation->instagramAccount->zernio_account_id,
                $body,
                (string) \Illuminate\Support\Str::uuid(),
            );

            $messageData = data_get($result, 'data', $result);
            $externalId = data_get($messageData, 'messageId');

            $conversation->messages()->create([
                'external_message_id' => is_string($externalId) ? $externalId : null,
                'direction' => 'outbound',
                'type' => $attachmentType ?: 'text',
                'text' => $data['message'] ?? null,
                'status' => 'sent',
                'source' => 'panel',
                'attachments' => $attachmentUrl ? [[
                    'type' => $attachmentType,
                    'url' => $attachmentUrl,
                    'filename' => $attachmentName,
                ]] : null,
                'sent_at' => now(),
            ]);

            $conversation->update(['last_message_at' => now(), 'needs_human' => false]);

            return back()->with('success', 'پیام با موفقیت ارسال شد.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['message' => 'ارسال پیام انجام نشد. وضعیت اتصال و اطلاعات پیام را بررسی کنید.']);
        }
    }

    public function archiveConversation(Conversation $conversation, ZernioClient $zernio)
    {
        $conversation->load('instagramAccount');
        if (! $conversation->instagramAccount) {
            return back()->withErrors(['conversation' => 'حساب این گفتگو در دسترس نیست.']);
        }

        try {
            $archived = $conversation->status !== 'archived';
            $zernio->archiveConversation(
                $conversation->zernio_conversation_id,
                $conversation->instagramAccount->zernio_account_id,
                $archived
            );
            $conversation->update(['status' => $archived ? 'archived' : 'open']);
            return back()->with('success', $archived ? 'گفتگو بایگانی شد.' : 'گفتگو از بایگانی خارج شد.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['conversation' => 'تغییر وضعیت گفتگو انجام نشد.']);
        }
    }

    public function reactMessage(Request $request, Conversation $conversation, string $message, ZernioClient $zernio)
    {
        $data = $request->validate(['emoji' => 'required|string|max:8']);
        $conversation->load('instagramAccount');
        if (! $conversation->instagramAccount) {
            return response()->json(['ok'=>false,'message'=>'حساب گفتگو در دسترس نیست.'], 422);
        }
        try {
            $zernio->addReaction($conversation->zernio_conversation_id, $message, $conversation->instagramAccount->zernio_account_id, $data['emoji']);
            return response()->json(['ok'=>true]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'واکنش ثبت نشد.'], 422);
        }
    }

    public function removeReaction(Conversation $conversation, string $message, ZernioClient $zernio)
    {
        $conversation->load('instagramAccount');
        if (! $conversation->instagramAccount) {
            return response()->json(['ok'=>false,'message'=>'حساب گفتگو در دسترس نیست.'], 422);
        }
        try {
            $zernio->removeReaction($conversation->zernio_conversation_id, $message, $conversation->instagramAccount->zernio_account_id);
            return response()->json(['ok'=>true]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'واکنش حذف نشد.'], 422);
        }
    }

    public function markHuman(Conversation $conversation)
    {
        $conversation->update(['needs_human' => ! $conversation->needs_human]);

        if ($conversation->contact) {
            $conversation->contact->update(['needs_human' => $conversation->needs_human]);
        }

        return back();
    }

    public function logs()
    {
        return view('logs.index', [
            'logs' => AutomationLog::with('automation')->latest()->paginate(50),
            'events' => WebhookEvent::latest()->paginate(20),
        ]);
    }

    public function settings(ZernioClient $zernio)
    {
        $accounts = InstagramAccount::where('status', 'active')->orderBy('username')->get();
        $health = null;
        $iceBreakers = [];
        $selected = $accounts->first();

        if ($selected) {
            try {
                $health = $zernio->getAccountHealth($selected->zernio_account_id);
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                $ib = $zernio->getIceBreakers($selected->zernio_account_id);
                $iceBreakers = data_get($ib, 'iceBreakers', data_get($ib, 'data.iceBreakers', []));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('settings.index', [
            'accounts' => $accounts,
            'selectedAccount' => $selected,
            'health' => $health,
            'iceBreakers' => is_array($iceBreakers) ? $iceBreakers : [],
            'globalEnabled' => AppSetting::getValue('automation_global_enabled', true),
            'hoursEnabled' => AppSetting::getValue('business_hours_enabled', false),
            'businessHours' => AppSetting::getValue('business_hours', []),
        ]);
    }

    public function accountHealth(Request $request, ZernioClient $zernio)
    {
        $data = $request->validate(['account_id' => ['required','integer','exists:instagram_accounts,id']]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        try {
            return response()->json(['ok'=>true,'data'=>$zernio->getAccountHealth($account->zernio_account_id)]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'وضعیت اتصال در دسترس نیست.'],422);
        }
    }

    public function iceBreakers(Request $request, ZernioClient $zernio)
    {
        $data = $request->validate([
            'account_id' => ['required','integer','exists:instagram_accounts,id'],
            'items' => ['nullable','array','max:4'],
            'items.*' => ['nullable','string','max:80'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        try {
            $items = array_values(array_filter(array_map(fn($v)=>trim((string)$v), $data['items'] ?? [])));
            $result = $zernio->saveIceBreakers($account->zernio_account_id, array_map(fn($q)=>['question'=>$q], $items));
            return back()->with('success','پرسش‌های شروع گفتگو ذخیره شد.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['ice_breakers'=>'ذخیره پرسش‌های شروع گفتگو انجام نشد.']);
        }
    }

    public function saveSettings(Request $r)
    {
        AppSetting::setValue('automation_global_enabled', $r->boolean('global_enabled'));
        AppSetting::setValue('business_hours_enabled', $r->boolean('hours_enabled'));

        $hours = [];
        for ($i = 0; $i < 7; $i++) {
            $hours[$i] = [
                'enabled' => $r->boolean("day_$i"),
                'from' => $r->input("from_$i", '09:00'),
                'to' => $r->input("to_$i", '18:00'),
            ];
        }

        AppSetting::setValue('business_hours', $hours);

        return back()->with('success', 'تنظیمات با موفقیت ذخیره شد.');
    }

    public function notifications()
    {
        return response()->json(Notification::latest()->take(30)->get());
    }

    private function syncNativeAutomation(Automation $automation): void
    {
        $triggerValue = $automation->trigger instanceof \BackedEnum ? $automation->trigger->value : (string) $automation->trigger;
        if (! in_array($triggerValue, ['comment', 'story_reply'], true)) {
            return;
        }

        try {
            $automation->load(['keywords', 'actions', 'account']);
            $response = app(ZernioAutomationSync::class)->sync($automation);
            $remoteId = data_get($response, 'id', data_get($response, '_id', data_get($response, 'commentAutomationId')));
            if ($remoteId) {
                $automation->update(['zernio_automation_id' => (string) $remoteId, 'execution_mode' => 'zernio']);
                return;
            }

            if ($automation->status === 'active') {
                throw new \RuntimeException('شناسه اتوماسیون خارجی از سرویس دریافت نشد.');
            }
        } catch (\Throwable $e) {
            report($e);
            $automation->update(['status' => 'paused']);
            session()->flash('warning', 'اتوماسیون ذخیره شد اما فعال نشد؛ اتصال حساب یا تنظیمات ارسال را بررسی کنید.');
        }
    }

    private function syncKeywords(Automation $automation, string $keywords, string $excluded, string $matchMode): void
    {
        $automation->keywords()->delete();
        $normalType = in_array($matchMode, ['exact', 'word', 'contains'], true) ? $matchMode : 'contains';

        foreach ($this->splitValues($keywords) as $keyword) {
            $automation->keywords()->create([
                'keyword' => $keyword,
                'match_type' => $normalType,
                'is_excluded' => false,
            ]);
        }

        foreach ($this->splitValues($excluded) as $keyword) {
            $automation->keywords()->create([
                'keyword' => $keyword,
                'match_type' => 'contains',
                'is_excluded' => true,
            ]);
        }
    }

    private function syncActions(Automation $automation, array $data): void
    {
        $automation->actions()->delete();
        $order = 0;
        $trigger = $automation->trigger->value ?? $automation->trigger;

        $publicReply = trim((string) ($data['public_reply'] ?? ''));
        if ($publicReply !== '') {
            $automation->actions()->create([
                'action' => 'public_reply',
                'sort_order' => $order++,
                'config' => ['message' => $publicReply],
            ]);
        }

        $goal = $data['goal'] ?? null;
        $message = '';
        $privateAction = false;
        if ($trigger === 'comment') {
            if ($goal !== 'comment_public') {
                $message = trim((string) ($data['private_reply'] ?? $data['dm_message'] ?? ''));
                $privateAction = true;
            }
        } else {
            $message = trim((string) ($data['dm_message'] ?? ''));
        }

        $quickReplies = $this->decodeJsonList($data['quick_replies_json'] ?? '');
        $buttons = $this->decodeJsonList($data['buttons_json'] ?? '');
        $template = $this->decodeJsonValue($data['template_json'] ?? '');

        $extras = [];
        if ($buttons) $extras['buttons'] = array_values($buttons);
        elseif ($quickReplies) $extras['quickReplies'] = array_values($quickReplies);
        if (is_array($template) && $template !== []) $extras['template'] = $template;
        if (!empty($data['message_tag'])) $extras['messageTag'] = $data['message_tag'];
        if (array_key_exists('link_preview', $data)) $extras['linkPreview'] = (bool) $data['link_preview'];

        $sequence = $this->decodeJsonList($data['dm_sequence_json'] ?? '');

        if ($trigger !== 'comment' && $sequence) {
            foreach ($sequence as $item) {
                if (!is_array($item)) continue;
                $type = (string) ($item['type'] ?? '');
                $text = trim((string) ($item['message'] ?? ''));
                $url = trim((string) ($item['attachmentUrl'] ?? ''));
                if (!in_array($type, ['text', 'media'], true)) continue;
                if ($type === 'text' && $text === '') continue;
                if ($type === 'media' && $url === '') continue;

                $config = $extras;
                if ($text !== '') $config['message'] = $text;
                if ($url !== '') {
                    $attachmentType = strtolower((string) ($item['attachmentType'] ?? 'file'));
                    $config['attachmentUrl'] = $url;
                    $config['attachmentType'] = in_array($attachmentType, ['image','video','audio','file'], true) ? $attachmentType : 'file';
                    if (!empty($item['attachmentName'])) $config['attachmentName'] = $item['attachmentName'];
                }

                if ($config !== []) {
                    $automation->actions()->create([
                        'action' => 'direct_message',
                        'sort_order' => $order++,
                        'config' => $config,
                    ]);
                }
            }
        } else {
            $attachmentUrl = trim((string) ($data['dm_attachment_url'] ?? ''));
            $attachmentType = strtolower(trim((string) ($data['dm_attachment_type'] ?? '')));
            $attachmentName = trim((string) ($data['dm_attachment_name'] ?? ''));
            $config = $extras;
            if ($message !== '') $config['message'] = $message;
            if ($attachmentUrl !== '' && $trigger !== 'comment') {
                $config['attachmentUrl'] = $attachmentUrl;
                $config['attachmentType'] = in_array($attachmentType, ['image','video','audio','file'], true) ? $attachmentType : 'file';
                if ($attachmentName !== '') $config['attachmentName'] = $attachmentName;
            }

            $actionType = $trigger === 'comment' ? ($privateAction ? 'private_reply' : null) : 'direct_message';
            if ($actionType && $config !== []) {
                $automation->actions()->create([
                    'action' => $actionType,
                    'sort_order' => $order++,
                    'config' => $config,
                ]);
            }
        }

        if (!empty($data['hide_comment'])) {
            $automation->actions()->create([
                'action' => 'hide_comment',
                'sort_order' => $order++,
                'config' => [],
            ]);
        }

        if (!empty($data['add_tag'])) {
            $automation->actions()->create([
                'action' => 'add_tag',
                'sort_order' => $order++,
                'config' => ['tag' => trim((string) $data['add_tag'])],
            ]);
        }
    }

    private function decodeJsonList(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function decodeJsonValue(?string $raw): mixed
    {
        if (!is_string($raw) || trim($raw) === '') return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function splitValues(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn ($item) => trim($item),
            preg_split('/\r?\n|,/', $value, -1, PREG_SPLIT_NO_EMPTY)
        )));
    }
}
