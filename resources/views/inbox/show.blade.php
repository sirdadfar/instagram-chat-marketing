@extends('layout')
@section('page_title','گفتگو')
@section('page_hint','پاسخ مستقیم و مدیریت وضعیت این مکالمه')
@php
    $contact=$conversation->contact;
    $account=$conversation->instagramAccount;
@endphp
@section('content')
<div class="chat-page-header"><a class="back-link" href="{{ route('inbox') }}">→ گفتگوها</a><div class="chat-page-actions"><span class="status-badge {{ $conversation->needs_human?'paused':'active' }}"><i></i>{{ $conversation->needs_human?'نیازمند پیگیری':'فعال' }}</span><form method="POST" action="{{ route('inbox.human',$conversation) }}">@csrf<button class="soft-button" type="submit">{{ $conversation->needs_human?'برداشتن پیگیری':'سپردن به اپراتور' }}</button></form><form method="POST" action="{{ route('inbox.archive',$conversation) }}">@csrf<button class="soft-button" type="submit">{{ $conversation->status==='archived'?'بازکردن بایگانی':'بایگانی' }}</button></form></div></div>
<div class="chat-layout-pro">
<section class="chat-panel">
<div class="chat-header-pro"><div class="chat-person"><div class="avatar-photo avatar-photo-xl">{{ mb_strtoupper(mb_substr($contact?->username ?: 'IG',0,2)) }}</div><div><strong>{{ '@'.($contact?->username ?: 'مخاطب') }}</strong><span>{{ $contact?->full_name ?: 'مخاطب اینستاگرام' }}</span></div></div><div class="chat-header-meta"><span>{{ $account?->username ? '@'.$account->username : 'حساب' }}</span><span>{{ $conversation->last_message_at?->diffForHumans() }}</span></div></div>
<div class="message-stream" id="messageStream">
@forelse($conversation->messages as $message)
@php($isOut=$message->direction==='outbound')
<div class="message-line {{ $isOut?'outbound':'inbound' }}" data-message-line>
<div class="message-bubble {{ $isOut?'outgoing':'incoming' }}" data-message-id="{{ $message->external_message_id }}">
@if($message->text)<div class="bubble-text">{!! nl2br(e($message->text)) !!}</div>@endif
@if($message->attachments)
<div class="message-attachments">@foreach($message->attachments as $att)<div class="message-attachment">@if(($att['type']??'')==='image')<img src="{{ $att['url']??'' }}" alt="رسانه">@elseif(($att['type']??'')==='video')<video controls preload="metadata" src="{{ $att['url']??'' }}"></video>@elseif(($att['type']??'')==='audio')<audio controls src="{{ $att['url']??'' }}"></audio>@else<a target="_blank" rel="noreferrer" href="{{ $att['url']??'#' }}">فایل پیوست ↗</a>@endif</div>@endforeach</div>
@endif
@if($isOut && $message->external_message_id)<div class="bubble-tools"><button type="button" class="reaction-button" data-react-message data-url="{{ route('inbox.message.react',[$conversation,$message->external_message_id]) }}" data-emoji="❤️">♡</button></div>@endif
<div class="bubble-time">{{ $message->sent_at?->format('H:i') }}</div>
</div></div>
@empty<div class="empty-chat"><span>✦</span><strong>هنوز پیامی در این گفتگو نیست</strong></div>@endforelse
</div>
<div class="composer-dock"><form method="POST" action="{{ route('inbox.send',$conversation) }}" enctype="multipart/form-data" id="inboxComposerForm">@csrf
@include('partials.message_attachment_composer',['prefix'=>'compose','automationTrigger'=>'direct_message'])
<div class="composer-textarea-wrap"><textarea name="message" id="inboxMessage" class="inbox-message-input" maxlength="10000" placeholder="پیام خود را بنویس…"></textarea><span class="char-counter" data-counter-for="#inboxMessage"></span></div>
<div class="inbox-interactive-panel"><div class="interactive-title"><strong>تعامل</strong><span>اختیاری</span></div><div class="quick-interactive-row"><label>پاسخ‌های سریع</label><input id="inboxQuickReplies" type="text" placeholder="مثلاً قیمت | سفارش | موجودی"></div><div class="quick-interactive-row"><label>دکمه‌ها</label><input id="inboxButtons" type="text" placeholder="عنوان | لینک، عنوان دوم | لینک دوم"></div><div class="interactive-options"><label class="toggle-row"><span><strong>پیش‌نمایش لینک</strong></span><input class="toggle-input" type="checkbox" name="link_preview" value="1"><span class="toggle-ui"></span></label><label class="toggle-row"><span><strong>پیگیری انسانی</strong></span><select name="message_tag" class="mini-select"><option value="">خاموش</option><option value="HUMAN_AGENT">HUMAN_AGENT</option></select></label></div><input type="hidden" name="quick_replies_json" id="inboxQuickJson"><input type="hidden" name="buttons_json" id="inboxButtonsJson"><input type="hidden" name="template_json" id="inboxTemplateJson"></div>
<div class="composer-bottom-row"><span class="composer-status-text">Enter برای رفتن به خط بعدی</span><button class="primary-action send-button" type="submit">ارسال <span>↗</span></button></div>
</form></div>
</section>
<aside class="profile-panel"><div class="profile-card-main"><div class="profile-avatar-large">{{ mb_strtoupper(mb_substr($contact?->username ?: 'IG',0,2)) }}</div><h2>{{ '@'.($contact?->username ?: 'مخاطب') }}</h2><p>{{ $contact?->full_name ?: 'بدون نام ثبت‌شده' }}</p><div class="profile-tags">@forelse($contact?->tags ?? [] as $tag)<span>{{ $tag->name }}</span>@empty<span>بدون برچسب</span>@endforelse</div></div><div class="profile-card"><div class="profile-title"><strong>اطلاعات</strong><span>مخاطب</span></div><div class="profile-grid"><div><small>وضعیت</small><b>{{ $conversation->status==='archived'?'بایگانی':'باز' }}</b></div><div><small>پیام‌ها</small><b>{{ $conversation->messages->count() }}</b></div><div><small>ورودی اخیر</small><b>{{ $conversation->last_inbound_at?->diffForHumans() ?: '—' }}</b></div><div><small>پیگیری</small><b>{{ $conversation->needs_human?'بله':'خیر' }}</b></div></div></div><div class="profile-card"><div class="profile-title"><strong>ابزار سریع</strong></div><div class="profile-quick-actions"><a href="{{ route('automations.create') }}">✦ ساخت اتوماسیون</a><a href="{{ route('comments') }}">◎ مدیریت نظرات</a><button type="button" data-copy="{{ $contact?->username ? '@'.$contact->username : '' }}">⧉ کپی نام کاربری</button></div></div></aside>
</div>
@endsection
