@extends('layout')
@section('page_title','گفتگوها')
@section('page_hint','پیام‌های ورودی، پیگیری انسانی و پاسخ سریع')
@section('content')
<div class="page-header-row"><div><div class="eyebrow">صندوق گفتگو</div><h1>گفتگوها</h1><p>گفتگوها را بررسی کن، پاسخ بده و وضعیت پیگیری را مدیریت کن.</p></div><a class="primary-action" href="{{ route('automations.create') }}">＋ ساخت پاسخ خودکار</a></div>
<div class="inbox-toolbar-pro"><div class="search-field big"><span>⌕</span><input type="search" id="inboxSearch" placeholder="جست‌وجوی نام کاربری یا متن پیام…"></div><div class="toolbar-segment"><a href="{{ route('inbox') }}" class="{{ !request('human')?'active':'' }}">همه</a><a href="{{ route('inbox',['human'=>1]) }}" class="{{ request('human')?'active':'' }}">نیازمند پیگیری <b>{{ \App\Models\Conversation::where('needs_human',true)->count() }}</b></a></div></div>
<div class="inbox-shell">
<div class="inbox-list-panel">
@forelse($conversations as $conversation)
@php($last=$conversation->messages->first())
<a class="inbox-row {{ request()->route('conversation')?->id === $conversation->id ? 'active' : '' }}" href="{{ route('inbox.show',$conversation) }}" data-inbox-row data-search="{{ Str::lower(($conversation->contact?->username ?? '').' '.($conversation->contact?->full_name ?? '').' '.($last?->text ?? '')) }}">
<div class="avatar-photo avatar-photo-lg">{{ mb_strtoupper(mb_substr($conversation->contact?->username ?: 'IG',0,2)) }}</div><div class="row-main"><div class="row-title"><strong>{{ '@'.($conversation->contact?->username ?: 'مخاطب') }}</strong>@if($conversation->needs_human)<span class="priority-pill">پیگیری</span>@endif</div><small>{{ Str::limit($last?->text ?: ($last?->type==='audio'?'پیام صوتی':'رسانه'),68) }}</small></div><div class="row-time">{{ $conversation->last_message_at?->diffForHumans() }}</div>@if($conversation->unread_count>0)<span class="unread-pill">{{ $conversation->unread_count }}</span>@endif
</a>
@empty<div class="empty-state"><div class="empty-icon">✉</div><strong>گفتگویی وجود ندارد</strong><p>پیام‌های جدید بعد از ثبت در این صفحه دیده می‌شوند.</p></div>@endforelse
</div>
<div class="inbox-empty-preview"><div class="empty-illustration"><span>✉</span><div></div><div></div></div><h2>یک گفتگو را انتخاب کن</h2><p>جزئیات مکالمه، اطلاعات مخاطب و ابزار پاسخ در سمت چپ هر گفتگو قرار می‌گیرد.</p></div>
</div>
@if($conversations->hasPages())<div class="pagination-wrap">{{ $conversations->links() }}</div>@endif
@endsection
