@extends('layout')
@section('page_title','داشبورد')
@section('page_hint','نمایی سریع از گفتگوها، اتوماسیون‌ها و فعالیت امروز')
@section('content')
@php($statusLabels=['active'=>'فعال','paused'=>'متوقف','draft'=>'پیش‌نویس'])
<div class="dashboard-hero-premium">
    <div class="hero-text"><div class="eyebrow">نمای کلی امروز</div><h1>نمای کلی عملیات</h1><p>وضعیت اتوماسیون‌ها، گفتگوها و فعالیت‌های اخیر را در یک صفحه ببین.</p><div class="hero-actions"><a class="primary-action" href="{{ route('automations.create') }}">＋ ساخت اتوماسیون</a><a class="soft-button light" href="{{ route('inbox') }}">مشاهده گفتگوها ←</a></div></div>
    <div class="hero-collage"><div class="collage-note tilt-a"><span>اتوماسیون فعال</span><strong>{{ $stats['active'] }}</strong><small>در حال اجرا</small></div><div class="collage-note tilt-b"><span>نیازمند پیگیری</span><strong>{{ $stats['human'] }}</strong><small>در انتظار پیگیری</small></div><div class="collage-window"><div class="window-top"><i></i><i></i><i></i></div><div class="window-bars"><span style="width:72%"></span><span style="width:49%"></span><span style="width:61%"></span><span style="width:34%"></span></div></div></div>
</div>

<div class="kpi-grid kpi-grid-premium">
@foreach([['automation','اتوماسیون‌ها',$stats['automations'],'کل قوانین','✦'],['active','فعال',$stats['active'],'در حال اجرا','●'],['runs','اجراها',$stats['runs'],'کل اجراهای ثبت‌شده','↗'],['failed','خطاها',$stats['failed'],'نیازمند بررسی','!'],['human','پیگیری انسانی',$stats['human'],'گفتگوهای باز','◎']] as $k)
<div class="kpi-card"><div class="kpi-icon kpi-{{ $k[0] }}">{{ $k[4] }}</div><div><span>{{ $k[1] }}</span><strong>{{ number_format($k[2]) }}</strong><small>{{ $k[3] }}</small></div></div>
@endforeach
</div>

<div class="dashboard-grid dashboard-grid-premium">
<section class="panel-card dashboard-section"><div class="section-head"><div><div class="eyebrow">گردش‌کارها</div><h2>اتوماسیون‌های اخیر</h2></div><a class="text-action" href="{{ route('automations.index') }}">همه <span>←</span></a></div><div class="dashboard-automation-list">
@forelse($automations as $a)
@php($tr=$a->trigger instanceof \BackedEnum?$a->trigger->value:(string)$a->trigger)
<div class="dashboard-automation-row"><div class="row-avatar">{{ $tr==='comment'?'◎':($tr==='story_reply'?'◌':'↗') }}</div><div class="row-main"><strong>{{ $a->name }}</strong><small>{{ $a->account?->username ? '@'.$a->account->username : 'حساب' }} • اولویت {{ $a->priority }}</small></div><span class="status-badge {{ $a->status }}"><i></i>{{ $statusLabels[$a->status] ?? $a->status }}</span><div class="row-actions"><a class="quick-icon" href="{{ route('automations.edit',$a) }}">✎</a><button type="button" class="quick-icon" data-confirm-form="dashboard-delete-{{ $a->id }}">×</button><form id="dashboard-delete-{{ $a->id }}" method="POST" action="{{ route('automations.destroy',$a) }}" hidden>@csrf @method('DELETE')</form></div></div>
@empty<div class="empty-state"><div class="empty-icon">✦</div><strong>هنوز اتوماسیونی نداری</strong><p>از یک الگوی ساده شروع کن.</p></div>@endforelse
</div></section>

<section class="panel-card dashboard-section"><div class="section-head"><div><div class="eyebrow">گفتگو</div><h2>آخرین مکالمه‌ها</h2></div><a class="text-action" href="{{ route('inbox') }}">صندوق گفتگو <span>←</span></a></div><div class="conversation-list-dashboard">
@forelse($conversations as $conversation)
<div class="conversation-row"><div class="avatar-photo">{{ mb_strtoupper(mb_substr($conversation->contact?->username ?: 'IG',0,2)) }}</div><div class="row-main"><strong>{{ '@'.($conversation->contact?->username ?: 'مخاطب') }}</strong><small>{{ Str::limit($conversation->messages->first()?->text ?: 'رسانه',70) }}</small></div><div class="row-time">{{ $conversation->last_message_at?->diffForHumans() }}</div>@if($conversation->needs_human)<span class="priority-dot" title="نیازمند پیگیری"></span>@endif</div>
@empty<div class="empty-state"><strong>گفتگویی ثبت نشده</strong><p>وقتی پیام جدیدی برسد، اینجا نمایش داده می‌شود.</p></div>@endforelse
</div></section>

<section class="panel-card dashboard-section wide"><div class="section-head"><div><div class="eyebrow">رویدادها</div><h2>آخرین فعالیت</h2></div><a class="text-action" href="{{ route('logs') }}">مشاهده رویدادها <span>←</span></a></div><div class="event-timeline">
@forelse($events as $event)<div class="event-line"><span class="event-dot"></span><div><strong>{{ $event->event_type }}</strong><small>{{ $event->created_at?->diffForHumans() }} • {{ $event->status }}</small></div><code>{{ substr((string)$event->event_id,0,18) }}</code></div>@empty<div class="empty-state"><strong>رویدادی وجود ندارد</strong></div>@endforelse
</div></section>
</div>
@endsection
