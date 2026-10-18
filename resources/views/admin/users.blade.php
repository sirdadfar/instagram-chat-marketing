@extends('layout')
@section('page_title','مدیریت کاربران')
@section('page_hint','نظارت بر کاربران، پیج‌ها و واگذاری مالکیت')
@section('content')
<div class="page-header-row"><div><div class="eyebrow">مدیریت سامانه</div><h1>کاربران و پیج‌ها</h1><p>فهرست کاربران، وضعیت آن‌ها و مالکیت پیج‌های اینستاگرام. ادمین به همه داده‌ها دسترسی دارد.</p></div></div>
@if(\Illuminate\Support\Facades\Session::get('reset_password_plain'))<div class="flash flash-danger"><span class="flash-icon">!</span><div><strong>رمز جدید این کاربر (فقط همین یک بار نمایش داده می‌شود):</strong><p style="font-family:monospace;direction:ltr">{{ session('reset_password_plain') }}</p></div></div>@endif
<div class="settings-grid settings-grid-pro">
<div class="settings-main">
<section class="editor-card"><div class="section-head"><div><div class="eyebrow">کاربران</div><h2>حساب‌های سامانه</h2></div><span class="soft-tag">{{ $counts['active'] }} فعال از {{ $counts['users'] }}</span></div>
<div class="accounts-side-list">@forelse($users as $u)<div><div class="account-avatar small">{{ mb_strtoupper(mb_substr($u->name,0,2)) }}</div><div><strong>{{ $u->name }} @if($u->is_admin)<span class="soft-tag green">ادمین</span>@endif @if(!$u->isActive())<span class="soft-tag">غیرفعال</span>@endif</strong><small style="direction:ltr">{{ '@'.$u->username }}</small><small style="direction:ltr">{{ $u->email }}</small><small>{{ $u->instagram_accounts_count }} پیج</small></div>
<div style="display:flex;gap:6px;flex-wrap:wrap">
@if($u->isActive() && $u->id !== auth()->id())<form method="POST" action="{{ route('admin.users.suspend',$u->id) }}">@csrf<button class="secondary-action" type="submit">غیرفعال</button></form>@endif
@if(!$u->isActive())<form method="POST" action="{{ route('admin.users.activate',$u->id) }}">@csrf<button class="secondary-action" type="submit">فعال‌سازی</button></form>@endif
@if($u->id !== auth()->id())<form method="POST" action="{{ route('admin.users.toggle-admin',$u->id) }}">@csrf<button class="secondary-action" type="submit">{{ $u->is_admin ? 'حذف دسترسی ادمین' : 'تبدیل به ادمین' }}</button></form>
<form method="POST" action="{{ route('admin.users.reset-password',$u->id) }}">@csrf<button class="secondary-action" type="submit">ریست رمز</button></form>
<form method="POST" action="{{ route('admin.users.destroy',$u->id) }}" onsubmit="return confirm('حذف این کاربر قطعی است. ادامه می‌دهید؟')">@csrf @method('DELETE')<button class="secondary-action" type="submit">حذف</button></form>@endif
</div></div>@empty<div class="empty-mini">کاربری ثبت نشده است.</div>@endforelse</div></section>

<section class="editor-card"><div class="section-head"><div><div class="eyebrow">پیج‌های اینستاگرام</div><h2>واگذاری مالکیت</h2><p>هر پیج مال به یک کاربر وصل است. پیج‌های بی‌مالک را به کاربر دلخواه واگذار کن.</p></div><span class="soft-tag">{{ $counts['pages'] }} پیج</span></div>
<div class="accounts-side-list">@forelse($pages as $page)<div><div class="account-avatar small">{{ mb_strtoupper(mb_substr($page->username ?: 'IG',0,2)) }}</div><div><strong>{{ $page->username ? '@'.$page->username : $page->zernio_account_id }}</strong><small>{{ $page->user?->name ?? 'بی‌مالک' }}</small><small style="direction:ltr">{{ $page->user?->email }}</small></div>
<form method="POST" action="{{ route('admin.accounts.assign') }}" style="display:flex;gap:6px">@csrf<input type="hidden" name="account_id" value="{{ $page->id }}"><select name="user_id" class="form-field" style="min-width:140px;padding:6px 8px"><option value="">بی‌مالک</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($page->user_id === $u->id)>{{ $u->name }} ({{ '@'.$u->username }})</option>@endforeach</select><button class="secondary-action" type="submit">واگذاری</button></form></div>@empty<div class="empty-mini">پیجی ثبت نشده است.</div>@endforelse</div></section>
</div>
<aside class="settings-side sticky-side">
<section class="editor-card"><div class="section-head"><div><div class="eyebrow">نمای کلی</div><h2>آمار سامانه</h2></div></div><div class="health-list"><div><span>کاربران</span><strong>{{ $counts['users'] }}</strong></div><div><span>کاربران فعال</span><strong>{{ $counts['active'] }}</strong></div><div><span>ادمین‌ها</span><strong>{{ $counts['admins'] }}</strong></div><div><span>پیج‌ها</span><strong>{{ $counts['pages'] }}</strong></div></div></section>
</aside>
</div>
@endsection
