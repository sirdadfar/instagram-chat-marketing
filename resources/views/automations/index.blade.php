@extends('layout')
@section('page_title','اتوماسیون‌ها')
@section('page_hint','قواعد پاسخ‌گویی و کارهای خودکار را از یکجا مدیریت کن')
@section('content')
@php
    $statusLabels=['active'=>'فعال','paused'=>'متوقف','draft'=>'پیش‌نویس'];
    $triggerLabels=['comment'=>'نظر','story_reply'=>'پاسخ استوری','direct_message'=>'پیام مستقیم'];
@endphp
<div class="page-header-row">
    <div>
        <div class="eyebrow">اتوماسیون</div>
        <h1>همه گردش‌کارها</h1>
        <p>وضعیت و تنظیمات هر اتوماسیون را مستقیم مدیریت کن.</p>
    </div>
    <div class="header-actions"><button type="button" class="soft-button" data-focus-search="#automationSearch">⌕ جست‌وجو</button><a class="primary-action" href="{{ route('automations.create') }}">＋ اتوماسیون جدید</a></div>
</div>

<div class="filter-strip">
    <button class="filter-chip active" type="button" data-automation-filter="all">همه <span>{{ $automations->total() }}</span></button>
    <button class="filter-chip" type="button" data-automation-filter="active">فعال</button>
    <button class="filter-chip" type="button" data-automation-filter="paused">متوقف</button>
    <button class="filter-chip" type="button" data-automation-filter="draft">پیش‌نویس</button>
    <div class="filter-search"><span>⌕</span><input id="automationSearch" type="search" placeholder="نام اتوماسیون یا حساب…"></div>
</div>

<div class="automation-grid" data-automation-grid>
@forelse($automations as $a)
    @php
        $trigger = $a->trigger instanceof \BackedEnum ? $a->trigger->value : (string)$a->trigger;
        $status = (string)$a->status;
        $actions = $a->actions->count();
        $target = $a->target_id ? ($a->target_type === 'story' ? 'یک استوری' : 'یک پست') : 'همه محتواها';
        $goalIcon = $trigger==='comment' ? '◎' : ($trigger==='story_reply' ? '◌' : '↗');
    @endphp
    <article class="automation-card automation-card-premium" data-automation-card data-status="{{ $status }}" data-search="{{ Str::lower($a->name.' '.($a->account?->username ?? '').' '.$triggerLabels[$trigger]) }}">
        <div class="automation-accent automation-accent-{{ $trigger }}"></div>
        <div class="automation-card-top">
            <div class="automation-title"><div class="flow-avatar large">{{ $goalIcon }}</div><div><strong>{{ $a->name }}</strong><span>{{ $a->account?->username ? '@'.$a->account->username : 'حساب اینستاگرام' }}</span></div></div>
            <div class="card-menu">
                <a href="{{ route('automations.edit',$a) }}" class="quick-icon" title="ویرایش">✎</a>
                <form method="POST" action="{{ route('automations.toggle',$a) }}">@csrf<button class="quick-icon" type="submit" title="{{ $status==='active'?'توقف':'فعال‌سازی' }}">{{ $status==='active'?'Ⅱ':'▶' }}</button></form>
                <button type="button" class="quick-icon danger-soft" data-confirm-form="form-delete-{{ $a->id }}" data-confirm-title="حذف اتوماسیون" data-confirm-text="«{{ $a->name }}» حذف می‌شود. این عملیات برگشت‌پذیر نیست." title="حذف">×</button>
                <form id="form-delete-{{ $a->id }}" method="POST" action="{{ route('automations.destroy',$a) }}" hidden>@csrf @method('DELETE')</form>
            </div>
        </div>

        <div class="automation-flow-preview">
            <div class="flow-node"><span>۱</span><div><strong>{{ $triggerLabels[$trigger] ?? $trigger }}</strong><small>شروع</small></div></div>
            <div class="flow-line"></div>
            <div class="flow-node"><span>۲</span><div><strong>{{ $target }}</strong><small>دامنه</small></div></div>
            <div class="flow-line"></div>
            <div class="flow-node"><span>۳</span><div><strong>{{ $actions }} اقدام</strong><small>نتیجه</small></div></div>
        </div>

        <div class="automation-meta">
            <span class="status-badge {{ $status }}"><i></i>{{ $statusLabels[$status] ?? $status }}</span>
            <span>اولویت {{ $a->priority }}</span>
            <span>{{ $a->runs_count ?? 0 }} اجرا</span>
            <span>{{ $a->logs_count ?? 0 }} ثبت</span>
        </div>
        <div class="automation-card-footer">
            <div class="automation-keywords">
                @forelse($a->keywords->where('is_excluded',false)->take(3) as $kw)<span>#{{ $kw->keyword }}</span>@empty<span class="muted">بدون کلیدواژه</span>@endforelse
            </div>
            <a class="text-action" href="{{ route('automations.edit',$a) }}">ویرایش سریع <span>←</span></a>
        </div>
    </article>
@empty
    <div class="empty-state span-full"><div class="empty-icon">✦</div><strong>هنوز اتوماسیونی ساخته نشده</strong><p>یک اتوماسیون ساده بساز و در صورت نیاز جزئیات آن را ویرایش کن.</p><a class="primary-action" href="{{ route('automations.create') }}">＋ ساخت اولین اتوماسیون</a></div>
@endforelse
</div>

@if($automations->hasPages())<div class="pagination-wrap">{{ $automations->links() }}</div>@endif
@endsection
