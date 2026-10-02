<?php
namespace App\Enums;

enum AutomationAction: string
{
    case PublicReply = 'public_reply';
    case PrivateReply = 'private_reply';
    case DirectMessage = 'direct_message';
    case AddTag = 'add_tag';
    case HideComment = 'hide_comment';
}
