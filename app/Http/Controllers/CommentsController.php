<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Services\Zernio\ZernioClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentsController extends Controller
{
    public function index(Request $request, ZernioClient $client)
    {
        $accounts = InstagramAccount::where('status', 'active')->orderBy('username')->get();
        return view('comments.index', [
            'accounts' => $accounts,
            'selectedAccount' => $accounts->firstWhere('id', (int) $request->integer('account_id')) ?? $accounts->first(),
        ]);
    }

    public function list(Request $request, ZernioClient $client): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'post_id' => ['required', 'string', 'max:255'],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        $account = InstagramAccount::whereKey($data['account_id'])->where('status', 'active')->firstOrFail();
        $query = [];
        if (!empty($data['cursor'])) $query['cursor'] = $data['cursor'];

        try {
            $result = $client->listComments($data['post_id'], $account->zernio_account_id, $query);
            return response()->json([
                'ok' => true,
                'comments' => data_get($result, 'comments', data_get($result, 'data.comments', [])),
                'paging' => data_get($result, 'pagination', data_get($result, 'data.pagination', [])),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'دریافت نظرها انجام نشد.'], 422);
        }
    }

    public function reply(Request $request, ZernioClient $client): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'post_id' => ['required', 'string', 'max:255'],
            'comment_id' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2200'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status', 'active')->firstOrFail();
        try {
            $result = $client->replyToComment($data['post_id'], $data['comment_id'], $account->zernio_account_id, $data['message']);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            report($e); return response()->json(['ok' => false, 'message' => 'پاسخ ارسال نشد.'], 422);
        }
    }

    public function privateReply(Request $request, ZernioClient $client): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required','integer','exists:instagram_accounts,id'],
            'post_id' => ['required','string','max:255'],
            'comment_id' => ['required','string','max:255'],
            'message' => ['required','string','max:2200'],
            'quick_replies_json' => ['nullable','string','max:10000'],
            'buttons_json' => ['nullable','string','max:10000'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status','active')->firstOrFail();
        $body = ['message' => $data['message']];
        $quick = json_decode($data['quick_replies_json'] ?? '', true);
        $buttons = json_decode($data['buttons_json'] ?? '', true);
        if (is_array($quick) && $quick) $body['quickReplies'] = array_values(array_slice($quick, 0, 13));
        if (is_array($buttons) && $buttons) $body['buttons'] = array_values(array_slice($buttons, 0, 3));
        try {
            $result = $client->privateReply($data['post_id'], $data['comment_id'], $account->zernio_account_id, $body);
            return response()->json(['ok'=>true,'data'=>$result]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>'پیام خصوصی ارسال نشد. ممکن است زمان پاسخ‌گویی یا سطح دسترسی این نظر محدود شده باشد.'],422);
        }
    }

    public function delete(Request $request, ZernioClient $client): JsonResponse
    {
        return $this->action($request, $client, 'delete');
    }

    public function hide(Request $request, ZernioClient $client): JsonResponse
    {
        return $this->action($request, $client, 'hide');
    }

    public function unhide(Request $request, ZernioClient $client): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'post_id' => ['required', 'string', 'max:255'],
            'comment_id' => ['required', 'string', 'max:255'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status', 'active')->firstOrFail();
        try {
            $result = $client->unhideComment($data['post_id'], $data['comment_id'], $account->zernio_account_id);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            report($e); return response()->json(['ok' => false, 'message' => 'نمایش نظر انجام نشد.'], 422);
        }
    }

    public function unlike(Request $request, ZernioClient $client): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'post_id' => ['required', 'string', 'max:255'],
            'comment_id' => ['required', 'string', 'max:255'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status', 'active')->firstOrFail();
        try {
            $result = $client->unlikeComment($data['post_id'], $data['comment_id'], $account->zernio_account_id);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'حذف پسندیدن انجام نشد.'], 422);
        }
    }

    public function like(Request $request, ZernioClient $client): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'post_id' => ['required', 'string', 'max:255'],
            'comment_id' => ['required', 'string', 'max:255'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status', 'active')->firstOrFail();
        try {
            $result = $client->likeComment($data['post_id'], $data['comment_id'], $account->zernio_account_id);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            report($e); return response()->json(['ok' => false, 'message' => 'پسندیدن نظر در دسترس نیست یا مجوز آن فعال نشده است.'], 422);
        }
    }

    private function action(Request $request, ZernioClient $client, string $action): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'post_id' => ['required', 'string', 'max:255'],
            'comment_id' => ['required', 'string', 'max:255'],
        ]);
        $account = InstagramAccount::whereKey($data['account_id'])->where('status', 'active')->firstOrFail();
        try {
            $result = match ($action) {
                'delete' => $client->deleteComment($data['post_id'], $data['comment_id'], $account->zernio_account_id),
                'hide' => $client->hideComment($data['post_id'], $data['comment_id'], $account->zernio_account_id),
                default => [],
            };
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            report($e); return response()->json(['ok' => false, 'message' => $action === 'delete' ? 'حذف نظر انجام نشد.' : 'مخفی‌کردن نظر انجام نشد.'], 422);
        }
    }
}
