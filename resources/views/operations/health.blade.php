@extends('layout')
@section('page_title','سلامت سیستم')
@section('page_hint','وضعیت کلی Automation، Webhook و پردازش‌ها را بررسی کن')
@section('content')
<div class="page-header-row"><div><div class="eyebrow">System Health</div><h1>سلامت سیستم</h1><p>شاخص‌های اصلی سرویس را قبل از انتشار یا رفع خطا بررسی کن.</p></div></div>
<div class="insight-grid insight-grid-large">
@foreach([['اتوماسیون‌ها',$automationCount],['اتوماسیون فعال',$activeAutomationCount],['مخاطبان',$contactCount],['Webhook',$webhookCount],['خطاهای ثبت‌شده',$failedCount],['در حال اجرا',$runningCount]] as $stat)
<div class="insight-box premium-insight"><span>{{ $stat[0] }}</span><strong>{{ number_format($stat[1]) }}</strong><p>وضعیت ثبت‌شده در دیتابیس</p></div>
@endforeach
</div>
<section class="panel-card"><div class="section-head"><div><div class="eyebrow">Debug</div><h2>آخرین خطاها</h2></div><span class="soft-tag">{{ $failedCount }} خطا</span></div>
<div class="event-table">@forelse($failed as $log)<div class="event-row"><span class="event-status failed"></span><div class="row-main"><strong>{{ $log->automation?->name ?: 'اتوماسیون' }}</strong><small>{{ $log->created_at?->format('Y/m/d H:i:s') }}</small></div><code>{{ Str::limit((string)($log->message ?? $log->error ?? 'خطای ثبت‌شده'),120) }}</code></div>@empty<div class="empty-state"><strong>خطای ثبت‌شده‌ای وجود ندارد</strong></div>@endforelse</div></section>
@endsection
