<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboxSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_composer_quick_replies_are_normalized_before_sending(): void
    {
        [$user, $conversation] = $this->conversation();

        $this->actingAs($user)
            ->withSession(['panel_authenticated' => true])
            ->postJson(route('inbox.send', $conversation), [
                'message' => 'سلام',
                'quick_replies_text' => 'قیمت | سفارش',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(function (Request $request): bool {
            $quickReplies = $request->data()['quickReplies'] ?? [];

            return count($quickReplies) === 2
                && $quickReplies[0]['title'] === 'قیمت'
                && $quickReplies[0]['payload'] === 'قیمت'
                && ! array_key_exists('content_type', $quickReplies[0]);
        });
    }

    public function test_buttons_are_sent_with_normalized_urls_and_postbacks(): void
    {
        [$user, $conversation] = $this->conversation();

        $this->actingAs($user)
            ->withSession(['panel_authenticated' => true])
            ->postJson(route('inbox.send', $conversation), [
                'message' => 'سلام',
                'buttons_text' => 'سایت | example.com/page، پشتیبانی',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(function (Request $request): bool {
            return ($request->data()['buttons'] ?? []) === [
                ['type' => 'url', 'title' => 'سایت', 'url' => 'https://example.com/page'],
                ['type' => 'postback', 'title' => 'پشتیبانی', 'payload' => 'پشتیبانی'],
            ];
        });
    }

    public function test_quick_replies_and_buttons_cannot_be_sent_together(): void
    {
        [$user, $conversation] = $this->conversation();

        $this->actingAs($user)
            ->withSession(['panel_authenticated' => true])
            ->postJson(route('inbox.send', $conversation), [
                'message' => 'سلام',
                'quick_replies_text' => 'قیمت',
                'buttons_text' => 'سایت | https://example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'پاسخ‌های سریع و دکمه‌ها هم‌زمان ارسال نمی‌شوند؛ یکی را خالی کن.'
            );

        Http::assertNothingSent();
    }

    public function test_an_invalid_button_url_is_rejected_before_sending(): void
    {
        [$user, $conversation] = $this->conversation();

        $this->actingAs($user)
            ->withSession(['panel_authenticated' => true])
            ->postJson(route('inbox.send', $conversation), [
                'message' => 'سلام',
                'buttons_text' => 'سایت | http://exa mple.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'لینک دکمه «سایت» معتبر نیست؛ آدرس کامل را با https:// وارد کن.'
            );

        Http::assertNothingSent();
    }

    public function test_cached_javascript_quick_reply_json_without_payload_is_repaired(): void
    {
        [$user, $conversation] = $this->conversation();

        $this->actingAs($user)
            ->withSession(['panel_authenticated' => true])
            ->postJson(route('inbox.send', $conversation), [
                'message' => 'سلام',
                'quick_replies_json' => json_encode([
                    ['content_type' => 'text', 'title' => 'بله'],
                ], JSON_UNESCAPED_UNICODE),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(function (Request $request): bool {
            $quickReplies = $request->data()['quickReplies'] ?? [];

            return count($quickReplies) === 1
                && $quickReplies[0] === ['title' => 'بله', 'payload' => 'بله'];
        });
    }

    /** @return array{User, Conversation} */
    private function conversation(): array
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'data' => ['messageId' => 'msg_1']], 200),
        ]);

        $user = User::create([
            'name' => 'Page Owner',
            'username' => 'page_owner',
            'email' => 'owner@example.com',
            'password' => 'secret-password',
            'status' => 'active',
        ]);
        Credentials::rememberUserKey($user, 'test-zernio-key');

        $account = InstagramAccount::create([
            'user_id' => $user->id,
            'zernio_account_id' => 'zernio_acc_1',
            'username' => 'page',
            'status' => 'active',
        ]);

        $contact = Contact::create([
            'instagram_account_id' => $account->id,
            'external_user_id' => 'ig_user_1',
            'username' => 'follower',
        ]);

        $conversation = Conversation::create([
            'instagram_account_id' => $account->id,
            'contact_id' => $contact->id,
            'zernio_conversation_id' => 'conv_1',
            'status' => 'open',
        ]);

        return [$user, $conversation];
    }
}
