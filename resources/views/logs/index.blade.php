@extends('layout')
@section('page_title','رویدادها')
@section('page_hint','اجرای اتوماسیون‌ها و رویدادهای دریافتی را بررسی کن')
@section('content')
<div class="page-header-row"><div><div class="eyebrow">ثبت رویداد</div><h1>رویدادها و گزارش اجرا</h1><p>برای هر اتفاق، نتیجه اجرا و خطاها را با جزئیات قابل پیگیری ببین.</p></div></div>
<div class="monitor-grid"><div class="monitor-card"><span>اجراهای موفق</span><strong>{{ $logs->filter(fn($log) => in_array($log->status, ['success','executed']))->count() }}</strong><small>در این صفحه</small></div><div class="monitor-card"><span>اجراهای ناموفق</span><strong>{{ $logs->where('status','failed')->count() }}</strong><small>در این صفحه</small></div><div class="monitor-card"><span>رویدادهای ورودی</span><strong>{{ $events->count() }}</strong><small>در این صفحه</small></div></div>
<div class="pagination-grid"><section class="panel-card"><div class="section-head"><div><div class="eyebrow">اجرای اتوماسیون</div><h2>گزارش‌ها</h2></div></div><div class="event-table">
@forelse($logs as $log)
@php
    $statusLabels = ['success' => 'موفق', 'executed' => 'موفق', 'failed' => 'ناموفق', 'gated' => 'منتظر تأیید فالو', 'not_matched' => 'شرایط برقرار نبود', 'blocked' => 'محدود شده', 'matched' => 'منطبق'];
    $actionResults = is_array($log->actions_result) ? $log->actions_result : [];
    $failedAction = collect($actionResults)->firstWhere('status','failed');
    $hasActionFailure = (bool) $failedAction;
@endphp
<div class="event-row">
    <span class="event-status {{ in_array($log->status, ['success','executed']) && !$hasActionFailure ? 'active' : ($log->status === 'failed' || $hasActionFailure ? 'failed' : '') }}"></span>
    <div class="row-main"><strong>{{ $log->automation?->name ?: 'اتوماسیون حذف‌شده' }}</strong><small>{{ $log->created_at?->format('Y/m/d H:i:s') }}</small></div>
    <span class="status-badge {{ in_array($log->status, ['success','executed']) && !$hasActionFailure ? 'active' : 'paused' }}"><i></i>{{ $hasActionFailure ? 'ناموفق' : ($statusLabels[$log->status] ?? $log->status) }}</span>
    <code>{{ $log->error ?: ($hasActionFailure ? ($failedAction['error'] ?? 'یکی از عملیات‌ها شکست خورده است') : ($log->event_type ?: 'ثبت اجرا')) }}</code>
    <details class="event-details"><summary>جزئیات اجرا</summary>
        @if($log->error)<p><strong>خطا:</strong> {{ $log->error }}</p>@endif
        @if($actionResults)
            <h4>نتیجه عملیات‌ها</h4>
            @foreach($actionResults as $action)
                <div class="action-result"><strong>{{ $action['action'] ?? 'عملیات' }}</strong> — {{ $action['status'] ?? 'نامشخص' }}
                    @if(!empty($action['error']))<p class="error-text">{{ $action['error'] }}</p>@endif
                    @if(!empty($action['reason']))<p>علت: {{ $action['reason'] }}</p>@endif
                </div>
            @endforeach
        @endif
        @if($log->conditions)
            <h4>بررسی شرط‌ها</h4><pre>{{ json_encode($log->conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif
        @if(!$log->error && !$actionResults && !$log->conditions)<p>برای این گزارش جزئیات بیشتری ذخیره نشده است. این مورد ممکن است مربوط به اجراهای قدیمی باشد.</p>@endif
    </details>
</div>
@empty<div class="empty-state"><strong>گزارشی ثبت نشده</strong></div>@endforelse
</div><div class="pagination-wrap">{{ $logs->links() }}</div></section>
<section class="panel-card"><div class="section-head"><div><div class="eyebrow">وب‌هوک</div><h2>رویدادهای ورودی</h2></div></div><div class="event-table">@forelse($events as $event)<div class="event-row"><span class="event-status active"></span><div class="row-main"><strong>{{ $event->event_type }}</strong><small>{{ $event->created_at?->diffForHumans() }}</small></div><span class="soft-tag">{{ $event->status }}</span><code>{{ $event->error ?: Str::limit((string)$event->event_id,22,'…') }}</code></div>@empty<div class="empty-state"><strong>رویدادی ثبت نشده</strong></div>@endforelse</div><div class="pagination-wrap">{{ $events->links() }}</div></section></div>
@endsection
