<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramPost;
use App\Models\InstagramStory;
use App\Services\Zernio\ZernioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    public function index(Request $request, ZernioClient $client)
    {
        $accounts = InstagramAccount::where('status', 'active')->orderBy('username')->get();
        $selected = $accounts->firstWhere('id', (int) $request->integer('account_id')) ?? $accounts->first();
        $posts = collect(); $stories = collect(); $notice = null;
        if ($selected) {
            try { $posts = $this->normalisePosts($client->listInstagramPosts($selected->zernio_account_id, ['limit' => 30])); }
            catch (\Throwable $e) { report($e); $notice = 'بارگذاری فهرست محتوا انجام نشد.'; }
            try { $stories = $this->normaliseStories($client->listInstagramStories($selected->zernio_account_id, ['limit' => 30])); }
            catch (\Throwable $e) { report($e); }
        }
        return view('content.index', compact('accounts', 'selected', 'posts', 'stories', 'notice'));
    }

    public function create()
    {
        $accounts = InstagramAccount::where('status', 'active')->orderBy('username')->get();
        return view('content.create', [
            'accounts' => $accounts,
            'selected' => $accounts->first(),
        ]);
    }

    public function store(Request $request, ZernioClient $client)
    {
        $data = $request->validate([
            'account_id' => ['required','integer','exists:instagram_accounts,id'],
            'content_type' => ['required','in:feed,carousel,reel,story'],
            'caption' => ['nullable','string','max:2200'],
            'publish_mode' => ['required','in:now,scheduled,draft'],
            'scheduled_at' => ['nullable','date'],
            'timezone' => ['required','string','max:64'],
            'media_items_json' => ['required','string'],
            'first_comment' => ['nullable','string','max:2200'],
            'comments_enabled' => ['nullable','boolean'],
            'share_to_feed' => ['nullable','boolean'],
            'mute_audio' => ['nullable','boolean'],
            'is_ai_generated' => ['nullable','boolean'],
            'is_paid_partnership' => ['nullable','boolean'],
            'branded_content_sponsors' => ['nullable','string','max:1000'],
            'collaborators' => ['nullable','string','max:1000'],
            'location_id' => ['nullable','string','max:100'],
            'reel_cover_url' => ['nullable','url','max:2048'],
            'thumb_offset' => ['nullable','integer','min:0','max:600000'],
            'audio_id' => ['nullable','string','max:255'],
            'audio_volume' => ['nullable','integer','min:0','max:100'],
            'video_volume' => ['nullable','integer','min:0','max:100'],
            'trial_strategy' => ['nullable','in:MANUAL,SS_PERFORMANCE'],
            'reel_title' => ['nullable','string','max:90'],
            'audio_name' => ['nullable','string','max:200'],
            'user_tags_json' => ['nullable','string','max:20000'],
        ]);

        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        $mediaItems = json_decode($data['media_items_json'], true);
        if (!is_array($mediaItems) || $mediaItems === []) return back()->withErrors(['media_items_json' => 'حداقل یک رسانه انتخاب کنید.'])->withInput();

        $limits = ['feed'=>1,'reel'=>1,'story'=>1,'carousel'=>10];
        if (count($mediaItems) > $limits[$data['content_type']]) return back()->withErrors(['media_items_json'=>'تعداد رسانه‌های انتخاب‌شده برای این نوع محتوا مجاز نیست.'])->withInput();
        if ($data['content_type'] === 'carousel' && count($mediaItems) < 2) return back()->withErrors(['media_items_json'=>'آلبوم باید حداقل دو رسانه داشته باشد.'])->withInput();
        if ($data['publish_mode'] === 'scheduled' && empty($data['scheduled_at'])) return back()->withErrors(['scheduled_at'=>'زمان انتشار را مشخص کنید.'])->withInput();

        $platformSpecificData = [];
        if ($data['content_type'] === 'story') $platformSpecificData['contentType'] = 'story';
        if ($data['content_type'] === 'reel') {
            $platformSpecificData['shareToFeed'] = $request->boolean('share_to_feed', true);
            if ($request->boolean('mute_audio')) $platformSpecificData['muteAudio'] = true;
            if (!empty($data['reel_cover_url'])) $platformSpecificData['instagramThumbnail'] = $data['reel_cover_url'];
            elseif (!empty($data['thumb_offset'])) $platformSpecificData['thumbOffset'] = (int) $data['thumb_offset'];
            if (!empty($data['audio_id'])) $platformSpecificData['audioConfiguration'] = [
                'audioId' => $data['audio_id'],
                'audioVolume' => (int) ($data['audio_volume'] ?? 100),
                'videoVolume' => (int) ($data['video_volume'] ?? 100),
            ];
            if (!empty($data['audio_name'])) $platformSpecificData['audioName'] = $data['audio_name'];
            if (!empty($data['reel_title'])) $platformSpecificData['title'] = $data['reel_title'];
            if (!empty($data['trial_strategy'])) $platformSpecificData['trialParams'] = ['graduationStrategy' => $data['trial_strategy']];
        }
        if ($request->boolean('mute_audio') && $data['content_type'] !== 'reel' && $data['content_type'] !== 'feed' && $data['content_type'] !== 'story') $platformSpecificData['muteAudio'] = true;
        if ($request->boolean('is_ai_generated')) $platformSpecificData['isAiGenerated'] = true;
        if ($request->boolean('is_paid_partnership')) $platformSpecificData['isPaidPartnership'] = true;
        if ($sponsors = $this->csv($data['branded_content_sponsors'] ?? '')) $platformSpecificData['brandedContentSponsors'] = $sponsors;
        if ($collaborators = array_slice($this->csv($data['collaborators'] ?? ''), 0, 3)) $platformSpecificData['collaborators'] = $collaborators;
        if (!empty($data['location_id'])) $platformSpecificData['locationId'] = $data['location_id'];
        if (!empty($data['user_tags_json'])) {
            $tags = json_decode($data['user_tags_json'], true);
            if (is_array($tags)) $platformSpecificData['userTags'] = array_values($tags);
        }
        if ($request->has('comments_enabled') && $data['content_type'] !== 'story') $platformSpecificData['commentsEnabled'] = $request->boolean('comments_enabled');
        if ($data['content_type'] !== 'story' && !empty($data['first_comment'])) $platformSpecificData['firstComment'] = $data['first_comment'];

        $body = [
            'content' => $data['content_type'] === 'story' ? '' : ($data['caption'] ?? ''),
            'mediaItems' => array_values(array_map(fn($m) => ['type' => $m['type'] ?? 'image', 'url' => $m['url'] ?? ''], $mediaItems)),
            'platforms' => [[
                'platform' => 'instagram',
                'accountId' => $account->zernio_account_id,
                'platformSpecificData' => $platformSpecificData,
            ]],
        ];
        if ($data['publish_mode'] === 'now') $body['publishNow'] = true;
        elseif ($data['publish_mode'] === 'scheduled') {
            $body['scheduledFor'] = now()->parse($data['scheduled_at'])->toIso8601String();
            $body['timezone'] = $data['timezone'];
        } else $body['draft'] = true;

        try {
            $result = $client->createPost($body);
            $post = data_get($result, 'post', data_get($result, 'data.post', []));
            $target = data_get($post, 'platforms.0', []);
            $platformPostId = data_get($target, 'platformPostId');
            $platformUrl = data_get($target, 'platformPostUrl');
            if ($platformPostId || $platformUrl) {
                InstagramPost::updateOrCreate([
                    'instagram_account_id' => $account->id,
                    'instagram_media_id' => $platformPostId,
                ], [
                    'zernio_post_id' => data_get($post, '_id'),
                    'permalink' => $platformUrl,
                    'caption' => $data['caption'] ?? '',
                    'media_type' => strtoupper($data['content_type']),
                    'media_url' => data_get($mediaItems, '0.url'),
                    'thumbnail_url' => data_get($mediaItems, '0.thumbnailUrl'),
                    'published_at' => $data['publish_mode'] === 'now' ? now() : null,
                    'metadata' => $post,
                ]);
            }
            return redirect()->route('content')->with('success', $data['publish_mode'] === 'draft' ? 'پیش‌نویس ذخیره شد.' : ($data['publish_mode'] === 'scheduled' ? 'محتوا برای زمان انتخاب‌شده زمان‌بندی شد.' : 'محتوا برای انتشار ارسال شد.'));
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['content' => 'انتشار محتوا انجام نشد. در صورت نمایش خطای اتصال، وضعیت حساب را بررسی کنید.'])->withInput();
        }
    }

    public function destroy(Request $request, ZernioClient $client)
    {
        $id = $request->validate(['post_id'=>['required','string','max:255'],'account_id'=>['required','integer','exists:instagram_accounts,id']]);
        $account = InstagramAccount::whereKey($id['account_id'])->where('status','active')->firstOrFail();
        try { $client->deletePost($id['post_id']); return back()->with('success','محتوا حذف یا لغو شد.'); }
        catch (\Throwable $e) { report($e); return back()->withErrors(['content'=>'حذف محتوا انجام نشد.']); }
    }

    public function audio(Request $request, ZernioClient $client)
    {
        $data = $request->validate([
            'account_id' => ['required','integer','exists:instagram_accounts,id'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        try {
            $result = $client->listInstagramAudio($account->zernio_account_id, ['limit' => $data['limit'] ?? 30]);
            $items = data_get($result, 'audio', data_get($result, 'audios', data_get($result, 'data.audio', data_get($result, 'data.audios', []))));
            $audio = collect(is_array($items) ? $items : [])->filter('is_array')->map(fn($item) => [
                'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['audioId'] ?? ''),
                'name' => (string) ($item['name'] ?? $item['title'] ?? $item['audioName'] ?? 'صدای بدون نام'),
                'artist' => (string) ($item['artist'] ?? $item['author'] ?? ''),
                'duration' => $item['duration'] ?? $item['durationSeconds'] ?? null,
                'cover' => $item['coverUrl'] ?? $item['thumbnailUrl'] ?? $item['imageUrl'] ?? null,
            ])->filter(fn($item) => $item['id'] !== '')->values();
            return response()->json(['ok'=>true,'items'=>$audio]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'فهرست صدا در دسترس نیست.'],422);
        }
    }

    public function like(Request $request, ZernioClient $client)
    {
        $data = $request->validate([
            'account_id'=>['required','integer','exists:instagram_accounts,id'],
            'post_id'=>['required','string','max:255'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        try {
            return response()->json(['ok'=>true,'data'=>$client->likePost($data['post_id'], $account->zernio_account_id)]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'پسندیدن این محتوا در دسترس نیست؛ ممکن است مجوز تعامل برای حساب فعال نباشد.'],422);
        }
    }

    public function unlike(Request $request, ZernioClient $client)
    {
        $data = $request->validate([
            'account_id'=>['required','integer','exists:instagram_accounts,id'],
            'post_id'=>['required','string','max:255'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        try {
            return response()->json(['ok'=>true,'data'=>$client->unlikePost($data['post_id'], $account->zernio_account_id)]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'لغو پسندیدن انجام نشد.'],422);
        }
    }

    public function timeline(Request $request, ZernioClient $client)
    {
        $data = $request->validate([
            'account_id'=>['required','integer','exists:instagram_accounts,id'],
            'post_id'=>['required','string','max:255'],
            'from_date'=>['nullable','date'],
            'to_date'=>['nullable','date'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        $query = [];
        if (!empty($data['from_date'])) $query['fromDate'] = $data['from_date'];
        if (!empty($data['to_date'])) $query['toDate'] = $data['to_date'];
        try {
            return response()->json(['ok'=>true,'data'=>$client->getPostTimeline($data['post_id'], $query)]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'آمار عملکرد محتوا در دسترس نیست.'],422);
        }
    }

    public function storyInsights(Request $request, ZernioClient $client)
    {
        $data = $request->validate(['account_id'=>['required','integer','exists:instagram_accounts,id'],'story_id'=>['required','string','max:255']]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        try { return response()->json(['ok'=>true,'data'=>$client->getStoryInsights($account->zernio_account_id, $data['story_id'])]); }
        catch (\Throwable $e) { report($e); return response()->json(['ok'=>false,'message'=>'آمار استوری در دسترس نیست.'],422); }
    }

    private function normalisePosts(array $response)
    {
        $items = data_get($response,'posts',data_get($response,'data.posts',[]));
        return collect(is_array($items) ? $items : [])->filter('is_array')->map(fn($p)=>[
            'id'=>(string)($p['id'] ?? $p['platformPostId'] ?? $p['_id'] ?? ''),
            'type'=>strtoupper((string)($p['mediaType'] ?? $p['mediaProductType'] ?? data_get($p,'mediaItems.0.type','IMAGE'))),
            'image'=>$p['thumbnailUrl'] ?? data_get($p,'mediaItems.0.thumbnailUrl') ?? data_get($p,'mediaItems.0.url') ?? $p['mediaUrl'] ?? null,
            'url'=>$p['permalink'] ?? $p['platformPostUrl'] ?? null,
            'caption'=>(string)($p['caption'] ?? $p['content'] ?? ''),
            'timestamp'=>$p['timestamp'] ?? $p['publishedAt'] ?? null,
            'status'=>strtolower((string)($p['status'] ?? $p['state'] ?? ($p['publishedAt'] ? 'published' : 'draft'))),
            'scheduledFor'=>$p['scheduledFor'] ?? $p['scheduled_for'] ?? null,
        ])->filter(fn($p)=>$p['id']!=='')->values();
    }

    private function normaliseStories(array $response)
    {
        $items = data_get($response,'stories',data_get($response,'data.stories',[]));
        return collect(is_array($items) ? $items : [])->filter('is_array')->map(fn($s)=>[
            'id'=>(string)($s['id'] ?? $s['_id'] ?? ''),
            'type'=>strtoupper((string)($s['mediaType'] ?? 'IMAGE')),
            'image'=>$s['thumbnailUrl'] ?? $s['mediaUrl'] ?? null,
            'url'=>$s['permalink'] ?? null,
            'timestamp'=>$s['timestamp'] ?? null,
        ])->filter(fn($s)=>$s['id']!=='')->values();
    }

    private function csv(string $raw): array
    {
        return array_values(array_filter(array_map(fn($v)=>trim($v), preg_split('/[\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY))));
    }
}
