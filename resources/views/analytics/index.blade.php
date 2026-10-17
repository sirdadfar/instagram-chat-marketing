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

