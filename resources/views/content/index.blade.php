@extends('layout')
@section('page_title','محتوا')
@section('page_hint','انتشار، زمان‌بندی و بررسی عملکرد محتوا از یک صفحه')
@section('content')
@php($typeLabels=['IMAGE'=>'تصویر','VIDEO'=>'ویدیو','REEL'=>'ریلز','CAROUSEL'=>'آلبوم','CAROUSEL_ALBUM'=>'آلبوم','STORY'=>'استوری'])
<div class="page-header-row">
    <div><div class="eyebrow">کتابخانه محتوا</div><h1>محتوا</h1><p>پست‌ها، ریلزها و استوری‌ها را ببین و کارهای اصلی را با چند کلیک انجام بده.</p></div>
    <a class="primary-action" href="{{ route('content.create') }}">＋ انتشار محتوا</a>
</div>
@if($notice)<div class="flash flash-danger"><span class="flash-icon">!</span><div><strong>بارگذاری کامل نشد</strong><p>{{ $notice }}</p></div></div>@endif
<div class="content-toolbar page-toolbar">
    <div class="select-shell"><span>حساب</span><select onchange="window.location='{{ route('content') }}?account_id='+this.value">@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($selected?->id===$account->id)>{{ '@'.($account->username ?: 'instagram') }}</option>@endforeach</select></div>
    <div class="filter-tabs" data-content-filter><button class="filter-chip active" type="button" data-filter="all">همه</button><button class="filter-chip" type="button" data-filter="posts">پست‌ها و ریلز</button><button class="filter-chip" type="button" data-filter="stories">استوری‌ها</button></div>
    <a class="ghost-action" href="{{ route('content') }}?account_id={{ $selected?->id }}">↻ تازه‌سازی</a>
</div>

<div class="content-board" data-content-board>
<div class="content-masonry">
@forelse($posts as $item)
<article class="media-card media-card-rich" data-content-kind="posts" data-media-type="{{ strtolower($item['type']) }}">
    <div class="media-card-cover"><img src="{{ $item['image'] ?: asset('img/media-placeholder.svg') }}" alt="{{ $typeLabels[$item['type']] ?? $item['type'] }}" loading="lazy"> <div class="media-overlay-top"><span class="media-type-pill">{{ $typeLabels[$item['type']] ?? $item['type'] }}</span><button type="button" class="round-glass" data-copy="{{ $item['url'] }}" title="کپی لینک">⧉</button></div><div class="media-overlay-bottom"><span>{{ $item['status']==='published'?'منتشر شده':($item['status']==='scheduled'?'زمان‌بندی شده':'پیش‌نویس') }}</span></div></div>
    <div class="media-card-body"><div class="media-card-date">{{ $item['timestamp'] ? \Carbon\Carbon::parse($item['timestamp'])->diffForHumans() : 'بدون تاریخ' }}</div><p>{{ Str::limit($item['caption'] ?: 'بدون توضیح', 120) }}</p><div class="media-card-actions"><a class="soft-button" href="{{ $item['url'] ?: '#' }}" target="_blank" rel="noreferrer">مشاهده ↗</a><button type="button" class="soft-button" data-content-like data-liked="0" data-post-id="{{ $item['id'] }}" data-account-id="{{ $selected?->id }}">♡ پسند</button><button type="button" class="soft-button" data-content-timeline data-post-id="{{ $item['id'] }}" data-account-id="{{ $selected?->id }}">↗ عملکرد</button><button type="button" class="soft-button" data-content-delete data-post-id="{{ $item['id'] }}" data-account-id="{{ $selected?->id }}">حذف</button></div></div>
</article>
@empty
<div class="empty-state span-full"><div class="empty-icon">▧</div><strong>محتوایی برای نمایش نیست</strong><p>هنوز محتوایی در این حساب پیدا نشد یا دسترسی خواندن آن برقرار نیست.</p><a class="primary-action" href="{{ route('content.create') }}">＋ انتشار اولین محتوا</a></div>
@endforelse

@foreach($stories as $item)
<article class="media-card media-card-story" data-content-kind="stories" data-media-type="story">
    <div class="media-card-cover story-cover"><img src="{{ $item['image'] ?: asset('img/media-placeholder.svg') }}" alt="استوری" loading="lazy"><div class="media-overlay-top"><span class="media-type-pill story">استوری</span><button type="button" class="round-glass" data-story-insights data-account-id="{{ $selected?->id }}" data-story-id="{{ $item['id'] }}">⌁</button></div><div class="story-ring"></div></div>
    <div class="media-card-body"><div class="media-card-date">{{ $item['timestamp'] ? \Carbon\Carbon::parse($item['timestamp'])->diffForHumans() : 'فعال یا اخیر' }}</div><p>آمار استوری را برای دیدن بازدید، دسترسی، پاسخ‌ها و تعاملات باز کن.</p><button class="soft-button full-width" type="button" data-story-insights data-account-id="{{ $selected?->id }}" data-story-id="{{ $item['id'] }}">نمایش آمار</button></div>
</article>
@endforeach
</div>
<aside class="content-side-panel"><div class="side-sticky">
<div class="side-title"><span class="eyebrow">نمای حساب</span><strong>{{ '@'.($selected?->username ?? 'instagram') }}</strong></div>
<div class="mini-profile"><div class="workspace-avatar">{{ mb_strtoupper(mb_substr($selected?->username ?: 'IG',0,2)) }}</div><div><strong>{{ $selected?->name ?: 'حساب اینستاگرام' }}</strong><small>{{ $posts->count() }} محتوا • {{ $stories->count() }} استوری</small></div></div>
<div class="side-action-grid"><a href="{{ route('content.create') }}" class="side-action-card"><span>＋</span><div><strong>انتشار جدید</strong><small>پست، ریلز، استوری</small></div></a><a href="{{ route('automations.create') }}" class="side-action-card"><span>✦</span><div><strong>اتوماسیون</strong><small>برای کامنت یا دایرکت</small></div></a><a href="{{ route('comments') }}" class="side-action-card"><span>◎</span><div><strong>مدیریت نظرات</strong><small>پاسخ و پاک‌سازی</small></div></a><a href="{{ route('analytics') }}" class="side-action-card"><span>↗</span><div><strong>تحلیل</strong><small>عملکرد حساب</small></div></a></div>
</div></aside>
</div>
<div class="mini-modal" data-content-timeline-modal hidden><div class="mini-modal-card"><button type="button" class="modal-close" data-content-timeline-close>×</button><div class="eyebrow">عملکرد محتوا</div><h3>روند عملکرد</h3><div class="insight-grid story-insights-grid" data-content-timeline-grid></div></div></div>
<div class="mini-modal" data-story-modal hidden><div class="mini-modal-card"><button type="button" class="modal-close" data-story-close>×</button><div class="eyebrow">آمار استوری</div><h3 data-story-modal-title>استوری</h3><div class="insight-grid story-insights-grid" data-story-modal-grid></div><div class="help-box" data-story-modal-extra>در حال دریافت…</div></div></div>
@endsection
