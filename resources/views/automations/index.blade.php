@extends('layout')
@section('page_title','اتوماسیون‌ها')
@section('page_hint','قواعد پاسخ‌گویی و کارهای خودکار را از یکجا مدیریت کن')
@section('content')
@php
    $statusLabels = ['active'=>'فعال','paused'=>'متوقف','draft'=>'پیش‌نویس'];
    $triggerLabels = ['comment'=>'نظر','story_reply'=>'پاسخ استوری','direct_message'=>'پیام مستقیم'];
    $actionLabels = ['public_reply'=>'پاسخ عمومی','private_reply'=>'پاسخ خصوصی','direct_message'=>'پیام دایرکت','add_tag'=>'افزودن برچسب','hide_comment'=>'پنهان‌کردن نظر'];

    $faNum = static function ($value): string {
        return str_replace(
            ['0','1','2','3','4','5','6','7','8','9'],
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
            (string) $value
        );
    };
    $faCount = static fn ($value): string => $faNum(number_format((float) $value, 0, '.', '٬'));

    $status = $filters['status'];
    $sort = $filters['sort'];
    $search = $filters['q'];
    $total = max(0, $statistics['total']);
    $activePercent = $total > 0 ? (int) round($statistics['active'] / $total * 100) : 0;
    $isFiltered = $status !== 'all' || $search !== '';

    $preserved = array_filter([
        'q' => $search !== '' ? $search : null,
        'sort' => $sort !== 'priority' ? $sort : null,
    ], static fn ($value) => $value !== null);
    $statusUrl = static fn (string $value): string => route('automations.index',
        $value === 'all' ? $preserved : array_merge($preserved, ['status' => $value])
    );
    $searchClearUrl = route('automations.index', array_merge(
        $status === 'all' ? [] : ['status' => $status],
        $sort !== 'priority' ? ['sort' => $sort] : []
    ));
    $statusCounts = ['all'=>$total, 'active'=>max(0, $statistics['active']), 'paused'=>max(0, $statistics['paused']), 'draft'=>max(0, $statistics['draft'])];
@endphp

<div class="automation-hub">
    <div class="page-header-row automation-page-head">
        <div>
            <div class="eyebrow">اتوماسیون</div>
            <h1>گردش‌کارهای خودکار</h1>
            <p>وضعیت هر قاعده را ببین، سریع فعال یا متوقف کن و جزئیاتش را مستقیم مدیریت کن.</p>
        </div>
        <div class="header-actions">
            <button type="button" class="soft-button" data-focus-search="#automationSearch">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                جست‌وجو
            </button>
            <a class="primary-action" href="{{ route('automations.create') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                اتوماسیون جدید
            </a>
        </div>
    </div>

    <section class="automation-stats" aria-label="خلاصه وضعیت اتوماسیون‌ها">
        <article class="automation-stat is-total">
            <div class="automation-stat-head">
                <span class="automation-stat-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 3.5 13H11l-1 9 9.5-11H13z"/></svg>
                </span>
                <span class="automation-stat-chip">مجموع</span>
            </div>
            <strong>{{ $faCount($statistics['total']) }}</strong>
            <span class="automation-stat-label">کل گردش‌کارها</span>
            <p>{{ $faCount($statistics['draft']) }} پیش‌نویس · {{ $faCount($statistics['paused']) }} متوقف</p>
        </article>

        <article class="automation-stat is-active">
            <div class="automation-stat-head">
                <span class="automation-stat-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5 10 17 19 7"/></svg>
                </span>
                <span class="automation-stat-chip">{{ $faNum($activePercent) }}٪</span>
            </div>
            <strong>{{ $faCount($statistics['active']) }}</strong>
            <span class="automation-stat-label">در حال اجرا</span>
            <div class="automation-stat-meter" aria-hidden="true"><i style="width:{{ $activePercent }}%"></i></div>
        </article>

        <article class="automation-stat is-runs">
            <div class="automation-stat-head">
                <span class="automation-stat-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V9M12 19V5M19 19v-7"/></svg>
                </span>
                <span class="automation-stat-chip">اجرا</span>
            </div>
            <strong>{{ $faCount($statistics['runs']) }}</strong>
            <span class="automation-stat-label">اجراهای ثبت‌شده</span>
            <p>از تمام گردش‌کارهای این فضای کاری</p>
        </article>

        <article class="automation-stat {{ $statistics['failed'] > 0 ? 'is-failed' : 'is-clean' }}">
            <div class="automation-stat-head">
                <span class="automation-stat-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.8 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <span class="automation-stat-chip">۷ روز</span>
            </div>
            <strong>{{ $faCount($statistics['failed']) }}</strong>
            <span class="automation-stat-label">خطاهای اخیر</span>
            <p>{{ $statistics['failed'] > 0 ? 'برای بررسی، رویدادها را باز کن' : 'همه‌چیز بدون خطا اجرا شده' }}</p>
        </article>
    </section>

    <div class="automation-toolbar">
        <nav class="automation-tabs" aria-label="فیلتر بر اساس وضعیت">
            @foreach([['all','همه'],['active','فعال'],['paused','متوقف'],['draft','پیش‌نویس']] as [$key,$label])
                <a class="automation-tab {{ $status === $key ? 'is-active' : '' }}"
                   {{ $status === $key ? 'aria-current="page"' : '' }}
                   href="{{ $statusUrl($key) }}">
                    {{ $label }}<b>{{ $faCount($statusCounts[$key]) }}</b>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('automations.index') }}" class="automation-tools" role="search">
            @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <div class="automation-search">
                <span aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </span>
                <input id="automationSearch" type="search" name="q" value="{{ $search }}" placeholder="نام اتوماسیون یا حساب…" autocomplete="off" enterkeyhint="search" aria-label="جست‌وجو در اتوماسیون‌ها">
                <button type="submit">جست‌وجو</button>
            </div>

            <label class="select-shell automation-sort">
                <span>مرتب‌سازی</span>
                <select name="sort" data-auto-submit aria-label="مرتب‌سازی اتوماسیون‌ها">
                    <option value="priority" @selected($sort === 'priority')>اولویت</option>
                    <option value="newest" @selected($sort === 'newest')>تازه‌ترین</option>
                    <option value="runs" @selected($sort === 'runs')>بیشترین اجرا</option>
                    <option value="name" @selected($sort === 'name')>نام</option>
                </select>
            </label>

            <div class="automation-view-toggle" role="group" aria-label="حالت نمایش">
                <button type="button" data-automation-view="grid" class="is-active" aria-pressed="true" title="نمایش کارتی" aria-label="نمایش کارتی">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>
                </button>
                <button type="button" data-automation-view="list" aria-pressed="false" title="نمایش فهرستی" aria-label="نمایش فهرستی">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
                </button>
            </div>
        </form>
    </div>

    <div class="automation-result-bar">
        <span aria-live="polite">
            @if($isFiltered)
                <strong>{{ $faCount($automations->total()) }}</strong> نتیجه از {{ $faCount($statistics['total']) }} گردش‌کار
            @else
                <strong>{{ $faCount($automations->total()) }}</strong> گردش‌کار ثبت‌شده
            @endif
        </span>
        @if($isFiltered)
            <div class="automation-active-filters">
                @if($search !== '')<span class="automation-filter-pill">«{{ $search }}»<a href="{{ $searchClearUrl }}" aria-label="حذف جست‌وجو">×</a></span>@endif
                @if($status !== 'all')<span class="automation-filter-pill">{{ $statusLabels[$status] }}<a href="{{ route('automations.index', $preserved) }}" aria-label="حذف فیلتر وضعیت">×</a></span>@endif
                <a class="text-action" href="{{ route('automations.index') }}">پاک کردن فیلترها</a>
            </div>
        @endif
    </div>

    <div class="automation-grid" data-automation-grid>
    @forelse($automations as $a)
        @php
            $trigger = $a->primaryTrigger();
            $triggerValues = $a->triggerValues();
            $triggerText = collect($triggerValues)->map(fn($value)=>$triggerLabels[$value] ?? $value)->implode(' + ');
            $cardStatus = (string) $a->status;
            $actionCount = $a->actions->count();
            $firstAction = $a->actions->first()?->action;
            $actionText = $actionCount === 0 ? 'بدون اقدام' : ($actionLabels[$firstAction] ?? 'اقدام');
            if ($actionCount > 1) $actionText .= ' +'.$faNum($actionCount - 1);
            $target = $a->target_id ? ($a->target_type === 'story' ? 'یک استوری' : 'یک پست') : 'همه محتواها';
            $keywords = $a->keywords->where('is_excluded', false)->values();
            $extraKeywords = max(0, $keywords->count() - 3);
            $triggerIcon = match($trigger) {
                'story_reply' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M10 8.8v6.4l5.5-3.2z"/></svg>',
                'direct_message' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3.5"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/></svg>',
                default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.6a8.2 8.2 0 0 1-8.4 8.1 9 9 0 0 1-3.8-.9L3.5 20l1.2-4.4a8.1 8.1 0 0 1-1.2-4.3A8.2 8.2 0 0 1 12 3.2a8.3 8.3 0 0 1 8.5 8.4Z"/></svg>',
            };
        @endphp
        <article class="automation-card automation-card-modern" data-status="{{ $cardStatus }}">
            <span class="automation-accent automation-accent-{{ $trigger }}" aria-hidden="true"></span>

            <header class="automation-card-head">
                <div class="automation-identity">
                    <span class="automation-glyph automation-glyph-{{ $trigger }}">{!! $triggerIcon !!}</span>
                    <div>
                        <h3><a href="{{ route('automations.edit',$a) }}">{{ $a->name }}</a></h3>
                        <p dir="auto">{{ $a->account?->username ? '@'.$a->account->username : 'حساب اینستاگرام' }}</p>
                    </div>
                </div>
                <div class="automation-card-actions">
                    <form method="POST" action="{{ route('automations.toggle',$a) }}" class="automation-toggle-form">
                        @csrf
                        <button type="submit" class="automation-switch" role="switch"
                                aria-checked="{{ $cardStatus === 'active' ? 'true' : 'false' }}"
                                aria-label="{{ $cardStatus === 'active' ? 'توقف اتوماسیون' : 'فعال‌سازی اتوماسیون' }}"
                                title="{{ $cardStatus === 'active' ? 'توقف' : 'فعال‌سازی' }}">
                            <span class="switch-track" aria-hidden="true"></span>
                        </button>
                    </form>
                    <a href="{{ route('automations.edit',$a) }}" class="quick-icon" title="ویرایش" aria-label="ویرایش {{ $a->name }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </a>
                    <button type="button" class="quick-icon danger-soft" data-confirm-form="form-delete-{{ $a->id }}" data-confirm-title="حذف اتوماسیون" data-confirm-text="«{{ $a->name }}» حذف می‌شود. این عملیات برگشت‌پذیر نیست." title="حذف" aria-label="حذف {{ $a->name }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                    </button>
                    <form id="form-delete-{{ $a->id }}" method="POST" action="{{ route('automations.destroy',$a) }}" hidden>@csrf @method('DELETE')</form>
                </div>
            </header>

            <div class="automation-flow">
                <div class="automation-flow-step">
                    <span class="flow-step-icon">{!! $triggerIcon !!}</span>
                    <div><small>محرک</small><strong title="{{ $triggerText }}">{{ $triggerText }}</strong></div>
                </div>
                <span class="automation-flow-arrow" aria-hidden="true">←</span>
                <div class="automation-flow-step">
                    <span class="flow-step-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 5"/></svg>
                    </span>
                    <div><small>دامنه</small><strong>{{ $target }}</strong></div>
                </div>
                <span class="automation-flow-arrow" aria-hidden="true">←</span>
                <div class="automation-flow-step">
                    <span class="flow-step-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 18-8-8 18-2-8z"/></svg>
                    </span>
                    <div><small>نتیجه</small><strong title="{{ $actionText }}">{{ $actionText }}</strong></div>
                </div>
            </div>

            <div class="automation-metrics">
                <div class="automation-metric"><span>اجرا</span><strong>{{ $faCount($a->runs_count ?? 0) }}</strong></div>
                <div class="automation-metric"><span>ثبت</span><strong>{{ $faCount($a->logs_count ?? 0) }}</strong></div>
                <div class="automation-metric"><span>اولویت</span><strong>{{ $faCount($a->priority) }}</strong></div>
            </div>

            <footer class="automation-card-footer">
                <div class="automation-keywords">
                    @forelse($keywords->take(3) as $kw)<span>#{{ $kw->keyword }}</span>@empty<span class="muted">بدون کلیدواژه</span>@endforelse
                    @if($extraKeywords > 0)<span class="keyword-more">+{{ $faNum($extraKeywords) }}</span>@endif
                </div>
                <div class="automation-footer-meta">
                    <span class="status-badge {{ $cardStatus }}"><i></i>{{ $statusLabels[$cardStatus] ?? $cardStatus }}</span>
                    <a class="text-action" href="{{ route('automations.edit',$a) }}">مدیریت <span>←</span></a>
                </div>
            </footer>
        </article>
    @empty
        <div class="empty-state span-full">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>
            </div>
            @if($isFiltered)
                <strong>نتیجه‌ای پیدا نشد</strong>
                <p>عبارت جست‌وجو یا فیلتر وضعیت را تغییر بده.</p>
                <a class="secondary-action" href="{{ route('automations.index') }}">پاک کردن فیلترها</a>
            @else
                <strong>هنوز اتوماسیونی ساخته نشده</strong>
                <p>یک اتوماسیون ساده بساز و در صورت نیاز جزئیات آن را ویرایش کن.</p>
                <a class="primary-action" href="{{ route('automations.create') }}">＋ ساخت اولین اتوماسیون</a>
            @endif
        </div>
    @endforelse
    </div>

    @if($automations->hasPages())<div class="pagination-wrap">{{ $automations->links() }}</div>@endif
</div>
@endsection
