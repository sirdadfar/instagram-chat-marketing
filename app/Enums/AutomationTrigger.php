<?php
namespace App\Enums;

enum AutomationTrigger: string
{
    case Comment = 'comment';
    case StoryReply = 'story_reply';
    case DirectMessage = 'direct_message';
}
