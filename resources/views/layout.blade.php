<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f3ef">
    <title>@yield('page_title', 'داشبورد') | مدیریت اینستاگرام</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
</head>
<body>
@php
    $layoutAccountCount = $accountCount ?? \App\Models\InstagramAccount::where('status', 'active')->count();
    $layoutActiveAutomationCount = $activeAutomationCount ?? \App\Models\Automation::where('status', 'active')->count();
    $layoutHumanConversationCount = $humanConversationCount ?? \App\Models\Conversation::where('needs_human', true)->count();
@endphp
<div class="app-shell" data-notifications-url="{{ route('notifications') }}">
    <div class="mobile-backdrop" data-mobile-backdrop></div>
    <aside class="sidebar" data-sidebar>
        <div class="sidebar-head">
            <a href="{{ route('dashboard') }}" class="brand">
                <span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" class="fill"/></svg></span>
                <span class="brand-copy"><strong>مدیریت اینستاگرام</strong><small>مرکز عملیات</small></span>
            </a>
            <button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="بستن منو">×</button>
        </div>

        <div class="workspace-card">
            <div class="workspace-avatar">IG</div>
            <div class="workspace-copy"><strong>فضای کاری</strong><span>{{ $layoutAccountCount }} حساب فعال</span></div>
            <span class="online-dot" title="فعال"></span>
        </div>

        <nav class="sidebar-nav" aria-label="منوی اصلی">
            <div class="nav-label">نمای کلی</div>
            <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 13h6V4H4zM14 20h6v-9h-6zM14 9h6V4h-6zM4 20h6v-3H4z"/></svg></span><span>داشبورد</span></a>

            <div class="nav-label nav-label-gap">عملیات</div>
            <a class="nav-item {{ request()->routeIs('automations.*') ? 'active' : '' }}" href="{{ route('automations.index') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M13 2 3.5 13H11l-1 9 9.5-11H13z"/></svg></span><span>اتوماسیون‌ها</span><em>{{ $layoutActiveAutomationCount }}</em></a>
            <a class="nav-item {{ request()->routeIs('content*') ? 'active' : '' }}" href="{{ route('content') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 5"/></svg></span><span>محتوا</span></a>
            <a class="nav-item {{ request()->routeIs('contacts') ? 'active' : '' }}" href="{{ route('contacts') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="7" r="3"/><path d="M16 4.5a3 3 0 0 1 0 5.9M19 20v-1a4 4 0 0 0-3-3.9"/></svg></span><span>مخاطبان</span></a>
            <a class="nav-item {{ request()->routeIs('inbox*') ? 'active' : '' }}" href="{{ route('inbox') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v11H7l-3 3z"/><path d="M8 9h8M8 12h5"/></svg></span><span>گفتگوها</span><em class="nav-count-danger">{{ $layoutHumanConversationCount }}</em></a>
            <a class="nav-item {{ request()->routeIs('comments*') ? 'active' : '' }}" href="{{ route('comments') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 5h14v10H8l-3 3z"/><path d="M8 9h8M8 12h5"/></svg></span><span>نظرات</span></a>

            <div class="nav-label nav-label-gap">گزارش و تنظیمات</div>
            <a class="nav-item {{ request()->routeIs('templates*') ? 'active' : '' }}" href="{{ route('templates') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><span>قالب پیام</span></a>
            <a class="nav-item {{ request()->routeIs('media-library*') ? 'active' : '' }}" href="{{ route('media-library') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m5 17 4-4 3 3 2-2 4 4"/></svg></span><span>کتابخانه رسانه</span></a>
            <a class="nav-item {{ request()->routeIs('analytics') ? 'active' : '' }}" href="{{ route('analytics') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 19V9M12 19V5M19 19v-7"/></svg></span><span>تحلیل و آمار</span></a>
            <a class="nav-item {{ request()->routeIs('logs') ? 'active' : '' }}" href="{{ route('logs') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><span>رویدادها</span></a>
            <a class="nav-item {{ request()->routeIs('system-health') ? 'active' : '' }}" href="{{ route('system-health') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6z"/><path d="m8 12 2.5 2.5L16 9"/></svg></span><span>سلامت سیستم</span></a>
            <a class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="m19 13 2 1-2 3-2-1-2 2v2h-4v-2l-2-2-2 1-2-3 2-1V9L3 8l2-3 2 1 2-2V2h4v2l2 2 2-1 2 3-2 1z"/></svg></span><span>تنظیمات</span></a>
        </nav>

        <div class="sidebar-footer">
            <div class="system-status-card"><div class="system-status-head"><span class="status-pulse"></span><strong>وضعیت سیستم</strong><span class="status-label">عادی</span></div><p>رویدادهای جدید به‌صورت خودکار ثبت و پردازش می‌شوند.</p></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="logout-button"><svg viewBox="0 0 24 24"><path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="m15 16 4-4-4-4M9 12h10"/></svg>خروج</button></form>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-start">
                <button type="button" class="icon-button mobile-menu" data-sidebar-open aria-label="باز کردن منو"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
                <div><div class="breadcrumb"><span>مرکز مدیریت</span><b>/</b><strong>@yield('page_title', 'داشبورد')</strong></div><p class="topbar-hint">@yield('page_hint', 'مدیریت سریع و دقیق فعالیت‌های اینستاگرام')</p></div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="icon-button" data-theme-toggle title="تغییر پوسته"><svg viewBox="0 0 24 24"><path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/><circle cx="12" cy="12" r="4"/></svg></button>
                <button type="button" class="icon-button notification-button" data-notifications title="اعلان‌ها"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>@if($layoutHumanConversationCount > 0)<span class="notification-dot"></span>@endif</button>
                <div class="notification-panel" data-notification-panel></div>
                <div class="topbar-status"><span class="online-dot"></span><span>فعال</span></div>
                <a class="primary-action topbar-create" href="{{ route('automations.create') }}">+ ساخت اتوماسیون</a>
            </div>
        </header>

        <div class="page-content">
            @if(session('success'))<div class="flash flash-success" data-flash><span class="flash-icon">✓</span><div><strong>انجام شد</strong><p>{{ session('success') }}</p></div><button type="button" data-dismiss>×</button></div>@endif
            @if($errors->any())<div class="flash flash-danger" data-flash><span class="flash-icon">!</span><div><strong>نیاز به بررسی</strong><p>{{ $errors->first() }}</p></div><button type="button" data-dismiss>×</button></div>@endif
            @yield('content')
        </div>
        <footer class="page-footer"><span>مرکز مدیریت اینستاگرام</span><span>•</span><span>نسخه خصوصی</span></footer>
    </main>
</div>
<div class="modal-backdrop" data-confirm-modal aria-hidden="true"><div class="modal-card"><div class="modal-icon danger">!</div><h3 data-confirm-title>حذف مورد</h3><p data-confirm-text>این مورد حذف خواهد شد.</p><div class="modal-actions"><button type="button" class="secondary-action" data-confirm-cancel>انصراف</button><button type="button" class="danger-action" data-confirm-submit>حذف</button></div></div></div>
<div class="toast-stack" data-toast-stack></div>
<script src="{{ asset('js/panel.js') }}"></script>
<script>if('serviceWorker' in navigator){navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(()=>{});}</script>
@stack('scripts')
</body>
</html>
