<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZernioClient
{
    public function request(): PendingRequest
    {
        $key = config('zernio.api_key');

        if (! $key) {
            throw new RuntimeException('ZERNIO_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('zernio.base_url'), ' /'))
            ->withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('zernio.timeout', 20))
            // Laravel 12 accepts an integer or Closure as the second argument.
            // Keep the backoff compatible with Laravel 12 instead of passing an array.
            ->retry(3, 1000, throw: false);
    }

    public function get(string $uri, array $query = []): array
    {
        return $this->request()->get($uri, $query)->throw()->json();
    }

    public function post(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request()->withHeaders($headers)->post($uri, $data)->throw()->json();
    }

    public function patch(string $uri, array $data = []): array
    {
        return $this->request()->patch($uri, $data)->throw()->json();
    }

    public function put(string $uri, array $data = []): array
    {
        return $this->request()->put($uri, $data)->throw()->json();
    }

    public function delete(string $uri, array $query = []): array
    {
        $response = $this->request()->delete($uri, $query)->throw();
        $json = $response->json();
        return is_array($json) ? $json : [];
    }

    public function createCommentAutomation(array $data): array
    {
        return $this->post('/comment-automations', $data);
    }

    public function updateCommentAutomation(string $id, array $data): array
    {
        return $this->patch('/comment-automations/' . rawurlencode($id), $data);
    }

    public function listAccounts(array $query = []): array
    {
        return $this->get('/accounts', $query);
    }

    public function listWorkflows(array $query = []): array
    {
        return $this->get('/workflows', $query);
    }

    public function getFollowStatus(string $accountId, string $userId, bool $refresh = true): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/follow-status/' . rawurlencode($userId), ['refresh' => $refresh ? 'true' : 'false']);
    }

    public function listConversations(array $query = []): array
    {
        return $this->get('/inbox/conversations', $query);
    }

    public function listMessages(string $conversationId, array $query = []): array
    {
        return $this->get('/inbox/conversations/' . rawurlencode($conversationId) . '/messages', $query);
    }

    public function listInstagramPosts(string $accountId, array $query = []): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/posts', $query);
    }

    public function listInstagramPostsViaSyncExternal(string $accountId): array
    {
        return $this->post('/posts/sync-external', ['accountId' => $accountId]);
    }

    public function getAccountHealth(string $accountId): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/health');
    }

    public function listInstagramStories(string $accountId, array $query = []): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/instagram/stories', $query);
    }

    public function replyToComment(string $postId, string $commentId, string $accountId, string $message): array
    {
        return $this->post('/inbox/comments/' . rawurlencode($postId), [
            'accountId' => $accountId,
            'commentId' => $commentId,
            'message' => $message,
        ]);
    }

    public function privateReply(string $postId, string $commentId, string $accountId, array $body): array
    {
        return $this->post(
            '/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId) . '/private-reply',
            array_merge(['accountId' => $accountId], $body),
        );
    }

    public function sendMessage(string $conversationId, string $accountId, array $body, string $idempotencyKey): array
    {
        return $this->post(
            '/inbox/conversations/' . rawurlencode($conversationId) . '/messages',
            array_merge(['accountId' => $accountId], $body),
            ['Idempotency-Key' => $idempotencyKey],
        );
    }

    public function hideComment(string $postId, string $commentId, string $accountId): array
    {
        return $this->post('/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId) . '/hide', [
            'accountId' => $accountId,
        ]);
    }

    public function configureWebhooks(array $data): array
    {
        return $this->post('/webhooks/settings', $data);
    }

    public function testWebhook(string $webhookId): array
    {
        return $this->post('/webhooks/test', ['webhookId' => $webhookId]);
    }


    public function createPost(array $data): array
    {
        return $this->post('/posts', $data);
    }

    public function deletePost(string $postId): array
    {
        return $this->delete('/posts/' . rawurlencode($postId));
    }

    public function likePost(string $postId, string $accountId): array
    {
        return $this->post('/inbox/posts/' . rawurlencode($postId) . '/like', ['accountId' => $accountId]);
    }

    public function unlikePost(string $postId, string $accountId): array
    {
        return $this->delete('/inbox/posts/' . rawurlencode($postId) . '/like', ['accountId' => $accountId]);
    }

    public function getPostTimeline(string $postId, array $query = []): array
    {
        return $this->get('/analytics/post-timeline', array_merge(['postId' => $postId], $query));
    }

    public function listComments(string $postId, string $accountId, array $query = []): array
    {
        return $this->get('/inbox/comments/' . rawurlencode($postId), array_merge(['accountId' => $accountId], $query));
    }

    public function createComment(string $postId, string $accountId, string $text): array
    {
        return $this->post('/inbox/comments/' . rawurlencode($postId), ['accountId' => $accountId, 'message' => $text]);
    }

    public function deleteComment(string $postId, string $commentId, string $accountId): array
    {
        return $this->delete('/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId), ['accountId' => $accountId]);
    }

    public function likeComment(string $postId, string $commentId, string $accountId): array
    {
        return $this->post('/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId) . '/like', ['accountId' => $accountId]);
    }

    public function unlikeComment(string $postId, string $commentId, string $accountId): array
    {
        return $this->delete('/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId) . '/like', ['accountId' => $accountId]);
    }

    public function unhideComment(string $postId, string $commentId, string $accountId): array
    {
        return $this->delete('/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId) . '/hide', ['accountId' => $accountId]);
    }

    public function addReaction(string $conversationId, string $messageId, string $accountId, string $emoji): array
    {
        return $this->post('/inbox/conversations/' . rawurlencode($conversationId) . '/messages/' . rawurlencode($messageId) . '/reactions', ['accountId' => $accountId, 'emoji' => $emoji]);
    }

    public function removeReaction(string $conversationId, string $messageId, string $accountId): array
    {
        return $this->delete('/inbox/conversations/' . rawurlencode($conversationId) . '/messages/' . rawurlencode($messageId) . '/reactions', ['accountId' => $accountId]);
    }

    public function archiveConversation(string $conversationId, string $accountId, bool $archived = true): array
    {
        $method = $archived ? 'post' : 'delete';
        return $this->{$method}('/inbox/conversations/' . rawurlencode($conversationId) . '/archive', ['accountId' => $accountId]);
    }

    public function getStoryInsights(string $accountId, string $storyId, array $query = []): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/instagram/stories/' . rawurlencode($storyId) . '/insights', $query);
    }

    public function getInstagramAccountInsights(string $accountId, array $query = []): array
    {
        return $this->get('/analytics/instagram/account-insights', array_merge(['accountId' => $accountId], $query));
    }

    public function getFollowerHistory(string $accountId, array $query = []): array
    {
        return $this->get('/analytics/instagram/follower-history', array_merge(['accountId' => $accountId], $query));
    }

    public function getDemographics(string $accountId, array $query = []): array
    {
        return $this->get('/analytics/instagram/demographics', array_merge(['accountId' => $accountId], $query));
    }

    public function getAnalyticsDelta(?string $cursor = null, array $query = []): array
    {
        $params = array_merge(['limit' => 100], $query);
        if ($cursor !== null && $cursor !== '') {
            $params['cursor'] = $cursor;
        }
        return $this->get('/analytics/delta', $params);
    }

    public function listInstagramAudio(string $accountId, array $query = []): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/instagram/audio', $query);
    }

    public function getIceBreakers(string $accountId): array
    {
        return $this->get('/accounts/' . rawurlencode($accountId) . '/instagram-ice-breakers');
    }

    public function saveIceBreakers(string $accountId, array $iceBreakers): array
    {
        return $this->put('/accounts/' . rawurlencode($accountId) . '/instagram-ice-breakers', ['iceBreakers' => array_values($iceBreakers)]);
    }

    public function deleteIceBreakers(string $accountId): array
    {
        return $this->delete('/accounts/' . rawurlencode($accountId) . '/instagram-ice-breakers');
    }

    /**
     * Upload an attachment through Zernio's direct media endpoint.
     * The returned public URL can be used as attachmentUrl in Inbox sends.
     */
    public function uploadMediaDirect(UploadedFile $file): array
    {
        $key = config('zernio.api_key');

        if (! $key) {
            throw new RuntimeException('ZERNIO_API_KEY is not configured.');
        }

        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded media.');
        }

        try {
            return Http::baseUrl(rtrim((string) config('zernio.base_url'), ' /'))
                ->withToken($key)
                ->acceptJson()
                ->timeout(max(30, (int) config('zernio.timeout', 20)))
                ->retry(3, 1000, throw: false)
                ->attach('file', $handle, $file->getClientOriginalName())
                ->post('/media/upload-direct', [
                    'contentType' => $file->getMimeType(),
                ])
                ->throw()
                ->json();
        } finally {
            fclose($handle);
        }
    }
}
