<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation;
use App\Services\Zernio\ZernioClient;
use App\Models\AppSetting;
use App\Support\FollowGate;

final class ZernioAutomationSync
{
    public function __construct(private ZernioClient $client)
    {
    }

    public function supports(Automation $automation): bool
    {
        return $automation->supportsNativeSync();
    }

    /** Pause the remote copy of an automation that moved to the local engine. */
    public function disable(Automation $automation): array
    {
        if (! $automation->zernio_automation_id) {
            return [];
        }

        $automation->loadMissing('account');
        // Use the page owner's key even when an admin edits a tenant automation.
        $this->client->forUser($automation->account?->user);

        return $this->client->updateCommentAutomation($automation->zernio_automation_id, ['isActive' => false]);
    }

    public function sync(Automation $automation): array
    {
        $automation->loadMissing(['keywords', 'actions', 'account']);

        // Native sync is always performed with the key of the page owner, not
        // the signed-in user. Admins edit tenant automations, and their own key
        // usually cannot manage another tenant's page.
        $this->client->forUser($automation->account?->user);

        if (! $this->supports($automation)) {
            throw new \RuntimeException('این نوع اتوماسیون از مسیر بومی پشتیبانی نمی‌شود.');
        }

        // Native comment automations accept exactly one trigger, and only a
        // single-trigger automation can reach this point.
        $trigger = $automation->primaryTrigger();
        $settings = is_array($automation->settings) ? $automation->settings : [];

        $keywords = $automation->keywords
            ->where('is_excluded', false)
            ->pluck('keyword')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $excluded = $automation->keywords
            ->where('is_excluded', true)
            ->pluck('keyword')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $dmMessage = null;
        $commentReply = null;
        $buttons = [];
        $quickReplies = [];
        $template = null;

        foreach ($automation->actions->sortBy('sort_order') as $action) {
            $config = is_array($action->config) ? $action->config : [];
            $message = trim((string) ($config['message'] ?? ''));

            if (in_array($action->action, ['private_reply', 'direct_message'], true)) {
                $dmMessage = $message;
            }
            if ($action->action === 'public_reply') {
                $commentReply = $message;
            }
            if (! empty($config['buttons']) && is_array($config['buttons'])) {
                $buttons = array_values($config['buttons']);
            }
            if (! empty($config['quickReplies']) && is_array($config['quickReplies'])) {
                $quickReplies = array_values($config['quickReplies']);
            }
            if (! empty($config['template']) && is_array($config['template'])) {
                $template = $config['template'];
            }
        }

        $matchMode = (string) ($settings['matchMode'] ?? $automation->match_mode ?? 'contains');
        if (! in_array($matchMode, ['exact', 'contains', 'word'], true)) {
            $matchMode = 'contains';
        }

        // A keyword-less native automation only exists when this automation's
        // own gate is on; a per-automation opt-out must keep its keywords.
        $keywordlessGate = in_array($trigger, ['comment', 'story_reply'], true)
            && FollowGate::enabled($automation)
            && (bool) AppSetting::getValue('follow_gate_without_keywords', false)
            && trim((string) $dmMessage) !== '';

        $body = [
            'name' => $automation->name,
            'accountId' => $automation->account->zernio_account_id,
            'trigger' => $trigger,
            'keywords' => $keywordlessGate ? [] : $keywords,
            'matchMode' => $matchMode,
            'excludeKeywords' => $excluded,
            'isActive' => $automation->status === 'active',
            'dmMessage' => $dmMessage ?? '',
            'commentReply' => $commentReply ?? '',
            'dmDelaySeconds' => (int) ($settings['dmDelaySeconds'] ?? 0),
            'commentReplyDelaySeconds' => (int) ($settings['commentReplyDelaySeconds'] ?? 0),
            'audience' => is_array($automation->audience) ? $automation->audience : [
                'followerStatus' => 'any',
                'whenUnknown' => 'send',
            ],
        ];

        if ($automation->target_id) {
            $body['platformPostId'] = $automation->target_id;
        }

        if (! empty($settings['dmMessageVariations'])) {
            $body['dmMessageVariations'] = array_values(array_slice((array) $settings['dmMessageVariations'], 0, 5));
        }
        if (! empty($settings['commentReplyVariations'])) {
            $body['commentReplyVariations'] = array_values(array_slice((array) $settings['commentReplyVariations'], 0, 5));
        }

        if (! empty($settings['alsoMatchInDms']) && $trigger === 'comment' && $keywords) {
            $body['alsoMatchInDms'] = true;
        }

        $linkTracking = (bool) ($settings['linkTracking'] ?? false);
        $body['linkTracking'] = $linkTracking;
        if ($linkTracking && ! empty($settings['clickTag'])) {
            $body['clickTag'] = trim((string) $settings['clickTag']);
        }

        // The platform accepts either flat buttons, quick replies or a generic template.
        // During an update we explicitly clear the previous mutually-exclusive content.
        $buttons = array_values(array_filter(array_map(
            static function ($button): ?array {
                if (!is_array($button)) {
                    return null;
                }

                $type = (string) ($button['type'] ?? '');
                $title = trim((string) ($button['title'] ?? ''));

                if ($title === '' || mb_strlen($title) > 20) {
                    return null;
                }

                if ($type === 'url') {
                    $url = trim((string) ($button['url'] ?? ''));
                    if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                        return null;
                    }

                    return ['type' => 'url', 'title' => $title, 'url' => $url];
                }

                if ($type === 'postback') {
                    $payload = trim((string) ($button['payload'] ?? ''));
                    if ($payload === '') {
                        return null;
                    }

                    return ['type' => 'postback', 'title' => $title, 'payload' => $payload];
                }

                return null;
            },
            $buttons
        )));
        $buttons = array_values(array_slice($buttons, 0, 3));

        if ($buttons) {
            if (mb_strlen((string) ($dmMessage ?? '')) > 640) {
                throw new \RuntimeException('برای استفاده از دکمه‌ها، متن پیام خصوصی باید حداکثر ۶۴۰ کاراکتر باشد.');
            }
            $body['buttons'] = $buttons;
            $body['template'] = null;
        } elseif ($quickReplies) {
            $body['quickReplies'] = array_values(array_slice($quickReplies, 0, 13));
            if ($automation->zernio_automation_id) {
                $body['buttons'] = [];
                $body['template'] = null;
            }
        } elseif ($template) {
            $body['template'] = $template;
            if ($automation->zernio_automation_id) {
                $body['buttons'] = [];
            }
        } elseif ($automation->zernio_automation_id) {
            $body['buttons'] = [];
            $body['template'] = null;
        }

        // Follow gate is a native Instagram audience rule:
        // only followers receive the main DM; unknown first-time commenters are asked to confirm after following.
        $hasConfiguredKeywords = count($keywords) > 0;
        $gateEnabled = in_array($trigger, ['comment', 'story_reply'], true)
            && FollowGate::enabled($automation)
            && trim((string) $dmMessage) !== ''
            && ($hasConfiguredKeywords || FollowGate::withoutKeywordsEnabled());

        if ($gateEnabled) {
            $existingAudience = is_array($automation->audience) ? $automation->audience : [];
            $body['audience'] = [
                'followerStatus' => 'follower',
                'whenUnknown' => 'verify',
            ];

            if (array_key_exists('minFollowerCount', $existingAudience) && $existingAudience['minFollowerCount'] !== null) {
                $body['audience']['minFollowerCount'] = (int) $existingAudience['minFollowerCount'];
            }

            $gate = FollowGate::config($automation);
            $body['followGate'] = [
                'message' => $gate['message'],
                'buttonLabel' => $gate['buttonLabel'],
                'notFollowingMessage' => $gate['notFollowingMessage'],
            ];
        }

        return $automation->zernio_automation_id
            ? $this->client->updateCommentAutomation($automation->zernio_automation_id, $body)
            : $this->client->createCommentAutomation($body);
    }
}
