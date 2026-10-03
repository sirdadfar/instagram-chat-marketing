@extends('layout')
@section('page_title','مخاطبان')
@section('page_hint','مخاطبان، برچسب‌ها و وضعیت پیگیری را مدیریت کن')
@section('content')
<div class="page-header-row">
    <div><div class="eyebrow">مخاطبان</div><h1>مدیریت مخاطبان</h1><p>پروفایل مخاطب، وضعیت فالو و پیگیری انسانی را یکجا ببین.</p></div>
</div>
<div class="filter-strip">
    <form class="filter-search" method="GET"><span>⌕</span><input name="q" value="{{ request('q') }}" placeholder="نام کاربری یا نام مخاطب…"></form>
    <a class="filter-chip {{ !request('human') ? 'active' : '' }}" href="{{ route('contacts') }}">همه <span>{{ AppModelsContact::count() }}</span></a>
    <a class="filter-chip {{ request('human') ? 'active' : '' }}" href="{{ route('contacts',['human'=>1]) }}">نیازمند پیگیری</a>
</div>
<div class="panel-card">
<div class="event-table">
@forelse($contacts as $contact)
<div class="event-row">
    <div class="avatar-photo avatar-photo-lg">{{ mb_strtoupper(mb_substr($contact->username ?: 'IG',0,2)) }}</div>
    <div class="row-main"><strong>{{ '@'.($contact->username ?: 'مخاطب') }}</strong><small>{{ $contact->full_name ?: 'نام ثبت نشده' }} · آخرین فعالیت {{ $contact->last_seen_at?->diffForHumans() ?: 'نامشخص' }}</small></div>
    <span class="soft-tag">{{ $contact->is_follower ? 'فالوور' : 'غیرفالوور' }}</span>
    @if($contact->needs_human)<span class="status-badge paused"><i></i>پیگیری</span>@endif
    <div>@foreach($contact->tags->take(3) as $tag)<span class="soft-tag">{{ $tag->name }}</span>@endforeach</div>
</div>
@empty<div class="empty-state"><strong>مخاطبی پیدا نشد</strong></div>@endforelse
</div>
<div class="pagination-wrap">{{ $contacts->links() }}</div>
</div>
@endsection
