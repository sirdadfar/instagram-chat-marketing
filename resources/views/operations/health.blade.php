@extends('layout')
@section('page_title','سلامت سیستم')
@section('page_hint','وضعیت کلی Automation، Webhook و پردازش‌ها را بررسی کن')
@section('content')
<div class="health-hero"><div class="health-shield">✓</div><div class="health-hero-copy"><span>پایش سیستم</span><h1>وضعیت و سلامت سیستم</h1><p>نمای کلی پردازش‌ها، اتوماسیون‌ها، رویدادهای ورودی و خطاهای اخیر.</p></div><div class="health-hero-side"><small>آخرین بررسی</small><strong>{{ now()->format('Y/m/d · H:i') }}</strong></div></div>
<div class="health-section-title"><div><span>نمای کلی سیستم</span><h2>شاخص‌های عملیاتی</h2></div><p>مقادیر فعلی ثبت‌شده در پایگاه داده</p></div>
<div class="insight-grid insight-grid-large health-kpi-grid">
@foreach([['اتوماسیون‌ها',$automationCount],['اتوماسیون فعال',$activeAutomationCount],['مخاطبان',$contactCount],['Webhook',$webhookCount],['خطاهای ثبت‌شده',$failedCount],['در حال اجرا',$runningCount]] as $stat)
<div class="insight-box premium-insight health-kpi"><span class="health-kpi-mark">{{ $loop->iteration }}</span><span>{{ $stat[0] }}</span><strong>{{ number_format($stat[1]) }}</strong><p>وضعیت ثبت‌شده در دیتابیس</p></div>
@endforeach
</div>
<section class="panel-card health-errors-panel"><div class="section-head"><div><div class="eyebrow">خطاهای اخیر</div><h2>آخرین خطاها</h2></div><span class="soft-tag">{{ $failedCount }} خطا</span></div>
<div class="event-table">@forelse($failed as $log)<div class="event-row"><span class="event-status failed"></span><div class="row-main"><strong>{{ $log->automation?->name ?: 'اتوماسیون' }}</strong><small>{{ $log->created_at?->format('Y/m/d H:i:s') }}</small></div><code>{{ Str::limit((string)($log->message ?? $log->error ?? 'خطای ثبت‌شده'),120) }}</code></div>@empty<div class="empty-state"><strong>خطای ثبت‌شده‌ای وجود ندارد</strong></div>@endforelse</div></section>
@endsection

