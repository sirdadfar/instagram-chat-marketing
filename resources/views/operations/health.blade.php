@extends('layout')
@section('page_title','سلامت سیستم')
@section('page_hint','وضعیت کلی Automation، Webhook و پردازش‌ها را بررسی کن')
@section('content')
<div class="health-hero"><div class="health-shield">✓</div><div class="health-hero-copy"><span>SYSTEM OBSERVABILITY</span><h1>وضعیت و سلامت سیستم</h1><p>نمای کلی پردازش‌ها، اتوماسیون‌ها، رویدادهای ورودی و خطاهای اخیر.</p></div><div class="health-hero-side"><small>آخرین بررسی</small><strong>{{ now()->format('Y/m/d · H:i') }}</strong></div></div>
<div class="health-section-title"><div><span>LIVE OVERVIEW</span><h2>شاخص‌های عملیاتی</h2></div><p>مقادیر فعلی ثبت‌شده در پایگاه داده</p></div>
<div class="page-header-row"><div><div class="eyebrow">System Health</div><h1>سلامت سیستم</h1><p>شاخص‌های اصلی سرویس را قبل از انتشار یا رفع خطا بررسی کن.</p></div></div>
<div class="insight-grid insight-grid-large health-kpi-grid">
@foreach([['اتوماسیون‌ها',$automationCount],['اتوماسیون فعال',$activeAutomationCount],['مخاطبان',$contactCount],['Webhook',$webhookCount],['خطاهای ثبت‌شده',$failedCount],['در حال اجرا',$runningCount]] as $stat)
<div class="insight-box premium-insight health-kpi"><span class="health-kpi-mark">{{ $loop->iteration }}</span><span>{{ $stat[0] }}</span><strong>{{ number_format($stat[1]) }}</strong><p>وضعیت ثبت‌شده در دیتابیس</p></div>
@endforeach
</div>
<section class="panel-card health-errors-panel"><div class="section-head"><div><div class="eyebrow">RECENT INCIDENTS</div><h2>آخرین خطاها</h2></div><span class="soft-tag">{{ $failedCount }} خطا</span></div>
<div class="event-table">@forelse($failed as $log)<div class="event-row"><span class="event-status failed"></span><div class="row-main"><strong>{{ $log->automation?->name ?: 'اتوماسیون' }}</strong><small>{{ $log->created_at?->format('Y/m/d H:i:s') }}</small></div><code>{{ Str::limit((string)($log->message ?? $log->error ?? 'خطای ثبت‌شده'),120) }}</code></div>@empty<div class="empty-state"><strong>خطای ثبت‌شده‌ای وجود ندارد</strong></div>@endforelse</div></section>
@endsection

<style>
.health-hero{display:flex;align-items:center;gap:18px;padding:26px 28px;margin-bottom:24px;border:1px solid rgba(148,163,184,.2);border-radius:22px;background:linear-gradient(120deg,rgba(16,185,129,.09),rgba(99,102,241,.07));box-shadow:0 10px 30px rgba(15,23,42,.035)}
.health-shield{width:56px;height:56px;display:grid;place-items:center;flex:none;border-radius:18px;background:#dcfce7;color:#15803d;font-size:28px;font-weight:900;box-shadow:inset 0 0 0 1px rgba(21,128,61,.12)}
.health-hero-copy>span,.health-section-title span{font-size:.67rem;letter-spacing:1.25px;font-weight:850;color:#64748b}
.health-hero h1{margin:5px 0 7px;font-size:clamp(1.25rem,2.2vw,1.8rem);font-weight:900}
.health-hero p{margin:0;color:var(--muted,#64748b);font-size:.88rem}
.health-hero-side{margin-right:auto;display:flex;flex-direction:column;gap:5px;padding:10px 13px;border:1px solid rgba(148,163,184,.22);border-radius:12px;background:rgba(255,255,255,.55);white-space:nowrap}
.health-hero-side small{color:#64748b;font-size:.72rem}.health-hero-side strong{font-size:.8rem}
.health-section-title{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin:25px 0 14px}
.health-section-title h2{font-size:1.1rem;margin:4px 0 0;font-weight:850}.health-section-title p{margin:0;color:var(--muted,#64748b);font-size:.8rem}
.health-kpi-grid{gap:15px}
.health-kpi{position:relative;min-height:142px;padding:20px!important;border:1px solid rgba(148,163,184,.2);box-shadow:0 8px 22px rgba(15,23,42,.035);overflow:hidden}
.health-kpi-mark{display:grid;place-items:center;width:29px;height:29px;margin-bottom:10px;border-radius:9px;background:rgba(99,102,241,.1);color:#6366f1;font-size:.76rem;font-weight:850}
.health-kpi strong{font-size:clamp(1.5rem,2.4vw,2rem)!important}
.health-errors-panel{margin-top:22px;border:1px solid rgba(148,163,184,.2);box-shadow:0 10px 28px rgba(15,23,42,.035)}
.health-errors-panel .event-row{padding:15px 0;border-bottom:1px solid rgba(148,163,184,.16)}
.health-errors-panel .event-row:last-child{border-bottom:0}
@media(max-width:700px){.health-hero{padding:19px 16px;gap:12px;flex-wrap:wrap;border-radius:17px}.health-shield{width:44px;height:44px;font-size:23px;border-radius:14px}.health-hero-side{width:100%;margin:7px 0 0;box-sizing:border-box}.health-section-title{align-items:flex-start;flex-direction:column}.health-section-title p{font-size:.75rem}.health-kpi{min-height:120px;padding:15px!important}}
</style>
