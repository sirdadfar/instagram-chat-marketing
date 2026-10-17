@extends('layout')
@section('page_title','تحلیل و آمار')
@section('page_hint','روند رشد، تعامل و رفتار مخاطبان را از یک صفحه ببین')
@section('content')
<div class="analytics-hero"><div class="analytics-hero-mark">↗</div><div><span class="analytics-kicker">مرکز تحلیل</span><h1>تصمیم‌گیری با داده‌های واقعی</h1><p>عملکرد حساب، رشد مخاطبان و کیفیت تعامل را در یک نگاه بررسی کن.</p></div><div class="analytics-hero-meta"><span class="analytics-live-dot"></span> گزارش تحلیلی <b>{{ now()->format('Y/m/d') }}</b></div></div>
<div class="page-header-row analytics-account-row"><div class="select-shell"><span>حساب اینستاگرام</span><select aria-label="انتخاب حساب اینستاگرام" onchange="window.location='{{ route('analytics') }}?account_id='+this.value">@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($selectedAccount?->id===$account->id)>{{ '@'.($account->username ?: 'instagram') }}</option>@endforeach</select></div></div>
@if(!empty($apiNotice))<div class="help-box">{{ $apiNotice }}</div>@endif
<div class="analytics-section-title"><div><span>عملکرد حساب</span><h2>شاخص‌های کلیدی حساب</h2></div><p>داده‌های دریافتی از اینستاگرام</p></div>
<div class="insight-grid insight-grid-large analytics-kpi-grid">
@php($metrics = is_array(data_get($insights,'metrics',[])) ? data_get($insights,'metrics',[]) : [])
@php($followsTotal = data_get($metrics,'follows_and_unfollows.total',0))
@foreach([['دسترسی',data_get($metrics,'reach.total',0),'افرادی که محتوا به آن‌ها رسیده'],['بازدید',data_get($metrics,'views.total',0),'دفعات مشاهده'],['تعامل',data_get($metrics,'accounts_engaged.total',0),'حساب‌های درگیر'],['تعاملات',data_get($metrics,'total_interactions.total',0),'مجموع تعامل'],['دنبال‌کردن',is_array($followsTotal)?(data_get($followsTotal,'follows',0)):0,'دنبال‌کننده‌های جدید'],['لغو دنبال‌کردن',is_array($followsTotal)?(data_get($followsTotal,'unfollows',0)):0,'کاهش دنبال‌کننده']] as $stat)
<div class="insight-box premium-insight analytics-kpi"><span class="analytics-kpi-icon">↗</span><span>{{ $stat[0] }}</span><strong>{{ is_numeric($stat[1])?number_format((float)$stat[1]):'—' }}</strong><p>{{ $stat[2] }}</p></div>
@endforeach
</div>
<div class="analytics-section-title"><div><span>رشد و مخاطبان</span><h2>روند رشد و شناخت مخاطب</h2></div><p>بر اساس داده‌های در دسترس</p></div>
<div class="analytics-grid-pro"><section class="panel-card"><div class="section-head"><div><div class="eyebrow">روند رشد</div><h2>دنبال‌کننده‌ها</h2></div><span class="soft-tag">{{ count($followers ?? []) }} نقطه</span></div><div class="chart-pro analytics-chart">@php($max=max(1,collect($followers ?? [])->max(fn($x)=>(float)data_get($x,'value',0))))@forelse($followers as $item)<div class="chart-col"><span class="chart-value">{{ number_format((float)data_get($item,'value',0)) }}</span><div class="chart-bar-wrap"><div class="chart-bar" style="height:{{ min(100,max(8,((float)data_get($item,'followers',data_get($item,'followerCount',0))/$max)*100)) }}%"></div></div><span>{{ Str::limit((string)data_get($item,'date',''),10,'') }}</span></div>@empty<div class="empty-state span-full"><strong>داده روند موجود نیست</strong></div>@endforelse</div></section>
<section class="panel-card"><div class="section-head"><div><div class="eyebrow">مخاطبان</div><h2>جمعیت‌شناسی</h2></div><span class="soft-tag">اختیاری</span></div><div class="demographics-grid">@foreach(['country'=>'کشور','city'=>'شهر','age'=>'سن','gender'=>'جنسیت'] as $key=>$label)<div class="demo-card"><strong>{{ $label }}</strong>@php($rows=data_get($demographics,'demographics.'.$key,[]))<div class="demo-list">@forelse(is_array($rows)?$rows:[] as $row)<div><span>{{ $row['dimension'] ?? $row['label'] ?? $row['name'] ?? '—' }}</span><b>{{ $row['value'] ?? $row['count'] ?? $row['percentage'] ?? 0 }}</b></div>@empty<span class="muted">داده‌ای برای نمایش نیست.</span>@endforelse</div></div>@endforeach</div></section></div>
@endsection

<style>
.analytics-hero{display:flex;align-items:center;gap:18px;padding:26px 28px;margin:0 0 26px;border-radius:22px;background:linear-gradient(115deg,#111827 0%,#172554 58%,#312e81 100%);color:#f8fafc;position:relative;overflow:hidden;box-shadow:0 16px 38px rgba(15,23,42,.16)}
.analytics-hero:after{content:"";position:absolute;width:230px;height:230px;border:1px solid rgba(255,255,255,.12);border-radius:50%;left:8%;top:-130px;box-shadow:0 0 0 28px rgba(255,255,255,.025),0 0 0 56px rgba(255,255,255,.02)}
.analytics-hero-mark{width:54px;height:54px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.12);font-size:30px;flex:none}
.analytics-hero h1{margin:5px 0 7px;font-size:clamp(1.25rem,2.2vw,1.8rem);color:#fff}
.analytics-hero p{margin:0;color:#cbd5e1;font-size:.9rem}
.analytics-kicker,.analytics-section-title span{font-size:.68rem;letter-spacing:1.4px;font-weight:800;color:#a5b4fc}
.analytics-hero-meta{margin-right:auto;align-self:flex-start;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.76rem;color:#e2e8f0;z-index:1}
.analytics-hero-meta b{padding:5px 8px;background:rgba(255,255,255,.1);border-radius:8px}
.analytics-live-dot{width:8px;height:8px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.14)}
.analytics-section-title{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin:25px 0 14px}
.analytics-section-title h2{margin:4px 0 0;font-size:1.1rem;font-weight:850}
.analytics-section-title span{color:#64748b;font-size:.65rem}
.analytics-section-title p{margin:0;color:var(--muted,#64748b);font-size:.8rem}
.analytics-kpi-grid{gap:15px}
.analytics-kpi{position:relative;overflow:hidden;min-height:145px;padding:20px!important;border:1px solid rgba(148,163,184,.2);box-shadow:0 8px 22px rgba(15,23,42,.035);transition:transform .18s ease,box-shadow .18s ease}
.analytics-kpi:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(15,23,42,.08)}
.analytics-kpi-icon{display:grid;place-items:center;width:31px;height:31px;border-radius:10px;background:rgba(99,102,241,.1);color:#6366f1;font-size:19px;margin-bottom:10px}
.analytics-kpi strong{font-size:clamp(1.45rem,2.4vw,2rem)!important;letter-spacing:-.5px}
.analytics-chart{min-height:235px}
.analytics-grid-pro>.panel-card{border:1px solid rgba(148,163,184,.2);box-shadow:0 10px 28px rgba(15,23,42,.035)}
.demographics-grid{gap:12px}
.demo-card{border:1px solid rgba(148,163,184,.2);border-radius:14px;padding:14px;background:rgba(148,163,184,.035)}
@media(max-width:700px){.analytics-hero{align-items:flex-start;padding:20px 17px;gap:12px;border-radius:17px;flex-wrap:wrap}.analytics-hero-mark{width:43px;height:43px;font-size:24px}.analytics-hero-meta{width:100%;margin:8px 0 0}.analytics-section-title{align-items:flex-start;flex-direction:column}.analytics-section-title p{font-size:.75rem}.analytics-kpi{min-height:125px;padding:15px!important}}
</style>
