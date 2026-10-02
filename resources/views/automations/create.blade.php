@extends('layout')
@section('page_title','ساخت اتوماسیون')
@section('page_hint','یک قانون ساده بساز؛ جزئیات پیشرفته را فقط هنگام نیاز باز کن')
@section('content')
<form method="POST" action="{{ route('automations.store') }}" id="automationCreateForm" class="automation-studio" novalidate>
@csrf
<input type="hidden" name="status" value="active">
<input type="hidden" name="execution_mode" id="executionModeField" value="zernio">
<input type="hidden" name="business_hours_only" value="0">
<input type="hidden" name="handoff_enabled" value="0">
<input type="hidden" name="target_type" id="targetTypeField" value="post">
<input type="hidden" name="target_id" id="targetIdField" value="">
<input type="hidden" name="quick_replies_json" id="quickRepliesJson">
<input type="hidden" name="buttons_json" id="buttonsJson">
<input type="hidden" name="template_json" id="templateJson">

<div class="studio-heading">
    <div><a class="back-link" href="{{ route('automations.index') }}">← بازگشت</a><div class="eyebrow">گردش‌کار جدید</div><h1>در چند قدم، پاسخ خودکار را بساز</h1><p>برای شروع فقط محرک، محتوای هدف و پاسخ را مشخص کن.</p></div>
    <div class="studio-heading-badge"><span>●</span> آماده ساخت</div>
</div>

<section class="preset-ribbon">
    <div class="preset-intro"><span class="preset-spark">✦</span><div><strong>شروع سریع</strong><small>یکی از الگوهای آماده را انتخاب کن تا فرم خودش تنظیم شود.</small></div></div>
    <button type="button" class="preset-card" data-preset="comment-dm"><span>💬</span><div><strong>کامنت → دایرکت</strong><small>پاسخ خصوصی بعد از کامنت</small></div></button>
    <button type="button" class="preset-card" data-preset="comment-public"><span>↩</span><div><strong>کامنت → پاسخ عمومی</strong><small>پاسخ مستقیم زیر کامنت</small></div></button>
    <button type="button" class="preset-card" data-preset="story-dm"><span>◌</span><div><strong>پاسخ استوری → دایرکت</strong><small>برای پاسخ‌های استوری</small></div></button>
    <button type="button" class="preset-card" data-preset="dm-reply"><span>✉</span><div><strong>دایرکت → پاسخ</strong><small>پاسخ سریع به پیام جدید</small></div></button>
</section>

<div class="studio-progress" data-studio-progress>
    <button type="button" class="progress-item active" data-go-step="1"><span>۱</span><b>محرک</b></button><i></i>
    <button type="button" class="progress-item" data-go-step="2"><span>۲</span><b>هدف</b></button><i></i>
    <button type="button" class="progress-item" data-go-step="3"><span>۳</span><b>پاسخ</b></button><i></i>
    <button type="button" class="progress-item" data-go-step="4"><span>۴</span><b>تنظیمات</b></button>
</div>

<div class="studio-shell studio-shell-clean">
<div class="studio-main">
<section class="studio-step is-active" data-step-panel="1">
    <div class="step-topline"><span class="step-index">۱</span><div><div class="eyebrow">محرک</div><h2>چه اتفاقی شروعش کند؟</h2><p>همان اتفاقی را انتخاب کن که باید باعث اجرای اتوماسیون شود.</p></div></div>
    <div class="choice-grid large-choice" data-trigger-picker>
        <label class="choice-card selected"><input type="radio" name="trigger" value="comment" checked><span class="choice-icon">💬</span><strong>نظر روی پست</strong><small>با ثبت یک کامنت، اتوماسیون فعال شود.</small></label>
        <label class="choice-card"><input type="radio" name="trigger" value="story_reply"><span class="choice-icon">◌</span><strong>پاسخ به استوری</strong><small>وقتی کسی به استوری پاسخ بدهد.</small></label>
        <label class="choice-card"><input type="radio" name="trigger" value="direct_message"><span class="choice-icon">✉</span><strong>پیام مستقیم</strong><small>با دریافت دایرکت جدید، پاسخ داده شود.</small></label>
    </div>
    <div class="goal-card" id="goalSection">
        <div><strong>برای کامنت چه کاری انجام شود؟</strong><span>می‌توانی پاسخ عمومی یا پیام خصوصی را انتخاب کنی.</span></div>
        <div class="goal-options">
            <label class="goal-option selected"><input type="radio" name="goal" value="comment_dm" checked><span>↗</span><b>پیام خصوصی</b><small>رایج برای قیمت، سفارش و لینک</small></label>
            <label class="goal-option"><input type="radio" name="goal" value="comment_public"><span>↩</span><b>پاسخ عمومی</b><small>پاسخ کوتاه زیر همان کامنت</small></label>
        </div>
    </div>
    <div class="step-actions"><span>مرحله ۱ از ۴</span><button type="button" class="primary-action" data-next-step="2">انتخاب هدف ←</button></div>
</section>

<section class="studio-step" data-step-panel="2">
    <div class="step-topline"><span class="step-index">۲</span><div><div class="eyebrow">هدف</div><h2 id="targetTitle">برای کدام محتوا؟</h2><p id="targetDescription">محتوا را تصویری انتخاب کن؛ هیچ شناسه‌ای لازم نیست.</p></div></div>
    <div class="account-inline-title"><span>حساب</span><small id="selectedAccountCaption">انتخاب نشده</small></div>
    <div class="account-grid compact-account" data-account-picker>
        @forelse($accounts as $account)
            <label class="account-choice {{ ($selectedAccount?->id===$account->id)?'selected':'' }}"><input type="radio" name="instagram_account_id" value="{{ $account->id }}" {{ ($selectedAccount?->id===$account->id)?'checked':'' }}><span class="account-avatar">{{ mb_strtoupper(mb_substr($account->username ?: 'IG',0,2)) }}</span><span class="account-copy"><strong>{{ '@'.($account->username ?: 'instagram') }}</strong><span>{{ $account->name ?: 'حساب فعال' }}</span></span><i>✓</i></label>
        @empty
            <div class="empty-state span-full"><strong>حساب فعال پیدا نشد</strong><p>اتصال حساب را بررسی کن و دوباره وارد این صفحه شو.</p></div>
        @endforelse
    </div>
    <div id="mediaStep" class="media-picker-card">
        <div class="content-toolbar media-toolbar"><div class="search-field"><span>⌕</span><input type="search" id="mediaSearch" placeholder="جست‌وجو در توضیحات محتوا…"></div><button type="button" class="secondary-action" id="refreshMedia">↻ تازه‌سازی</button><button type="button" class="ghost-action" id="selectAllMedia">همه محتوا</button></div>
        <div class="media-grid media-grid-studio" id="automationMediaGrid"><div class="empty-state span-full"><div class="spinner"></div><strong>در حال بارگذاری محتوا</strong><p>بعد از انتخاب حساب، تصاویر اینجا نمایش داده می‌شوند.</p></div></div>
        <div class="media-selection-summary"><span class="selection-check">✓</span><div><strong id="selectedMediaLabel">همه محتوا</strong><span id="selectedMediaMeta">برای اعمال روی همه محتواها، کارت خاصی انتخاب نکن.</span></div></div>
    </div>
    <div class="step-actions"><button type="button" class="ghost-action" data-prev-step="1">→ مرحله قبل</button><span>مرحله ۲ از ۴</span><button type="button" class="primary-action" data-next-step="3">نوشتن پاسخ ←</button></div>
</section>

<section class="studio-step" data-step-panel="3">
    <div class="step-topline"><span class="step-index">۳</span><div><div class="eyebrow">پاسخ</div><h2>چه پیامی ارسال شود؟</h2><p>متن را طبیعی و کوتاه بنویس؛ متغیرها را در صورت نیاز با یک کلیک اضافه کن.</p></div></div>
    <div class="form-grid">
        <div class="form-field"><label>نام اتوماسیون</label><input name="name" required maxlength="120" value="پاسخ خودکار جدید" placeholder="مثلاً پاسخ قیمت محصول"></div>
        <div class="form-field"><label>یادداشت داخلی <span class="muted">اختیاری</span></label><input name="description" maxlength="1000" placeholder="مثلاً مربوط به کمپین مهر"></div>
        <div class="form-field full" id="keywordsWrap"><label>کلمات فعال‌کننده <span class="muted">اختیاری</span></label><input name="keywords" placeholder="قیمت، سفارش، خرید"><small>اگر خالی باشد، رویداد بدون شرط واژه بررسی می‌شود.</small></div>
        <div class="form-field full" id="publicReplyWrap"><label>پاسخ عمومی <span class="muted">اختیاری</span></label><textarea name="public_reply" id="publicReply" maxlength="2200" placeholder="مثلاً حتماً، اطلاعات سفارش در دایرکت براتون ارسال شد."></textarea></div>
        <div class="form-field full" id="privateReplyWrap"><label id="messageLabel">پیام خصوصی</label><div class="message-editor"><textarea name="dm_message" id="privateReply" maxlength="10000" placeholder="سلام @{{username}}، ممنون که پیام دادی…"></textarea><div class="editor-foot"><div class="token-row"><button type="button" class="token-chip" data-insert-target="#privateReply" data-insert-token="@{{username}}">نام کاربر</button><button type="button" class="token-chip" data-insert-target="#privateReply" data-insert-token="@{{comment}}">متن کامنت</button><button type="button" class="token-chip" data-insert-target="#privateReply" data-insert-token="@{{post_url}}">لینک پست</button></div><span class="char-counter" data-counter-for="#privateReply">۰ / ۱۰۰۰۰</span></div></div><input type="hidden" name="private_reply" id="privateReplyMirror">
            @include('partials.message_attachment_composer',['prefix'=>'dm','automationTrigger'=>'comment'])
        </div>
        <div class="follow-gate-card" id="followGateCard" hidden>
            <div class="follow-gate-head"><div><span class="eyebrow">شرط دنبال‌کردن</span><strong>قبل از ارسال پیام اصلی، دنبال‌کردن صفحه را بررسی کن</strong><p>اگر مخاطب دنبال‌کننده نباشد، ابتدا پیام راهنما را می‌بیند و بعد از تأیید، وضعیت دنبال‌کردن بررسی می‌شود.</p></div><label class="toggle-row follow-gate-toggle"><span><strong>فعال باشد</strong><small>فقط برای پیام خصوصی کامنت</small></span><input class="toggle-input" type="checkbox" name="follow_gate_enabled" value="1" id="followGateEnabled"><span class="toggle-ui"></span></label></div>
            <div class="follow-gate-flow"><span>کامنت</span><b>→</b><span>پیام راهنما</span><b>→</b><span>بررسی دنبال‌کردن</span><b>→</b><span>پیام اصلی</span></div>
            <div class="follow-gate-fields">
                <div class="form-field"><label>پیام قبل از بررسی</label><textarea name="follow_gate_message" id="followGateMessage" maxlength="640" placeholder="برای دریافت اطلاعات، لطفاً ابتدا صفحه را دنبال کنید و سپس روی دکمه بررسی بزنید."></textarea></div>
                <div class="form-field"><label>متن دکمه</label><input name="follow_gate_button_label" id="followGateButtonLabel" maxlength="20" value="بررسی کردم" placeholder="بررسی کردم"></div>
                <div class="form-field full"><label>پیام اگر هنوز دنبال نکرده بود</label><textarea name="follow_gate_not_following_message" id="followGateNotFollowingMessage" maxlength="640" placeholder="بعد از دنبال‌کردن صفحه، دوباره روی «بررسی کردم» بزنید."></textarea></div>
            </div>
            <div class="follow-gate-preview"><div class="follow-gate-preview-top"><span class="preview-avatar">IG</span><div><strong>صفحه شما</strong><small>پیام خودکار</small></div></div><div class="follow-gate-preview-bubble" id="followGatePreviewMessage">لطفاً ابتدا صفحه را دنبال کنید و سپس روی «بررسی کردم» بزنید.</div><button type="button" class="follow-gate-preview-button" id="followGatePreviewButton">بررسی کردم</button><small class="follow-gate-preview-note">این مرحله قبل از پیام اصلی اجرا می‌شود.</small></div>
        </div>
    </div>
    <div class="step-actions"><button type="button" class="ghost-action" data-prev-step="2">→ مرحله قبل</button><span>مرحله ۳ از ۴</span><button type="button" class="primary-action" data-next-step="4">تنظیمات نهایی ←</button></div>
</section>

<section class="studio-step" data-step-panel="4">
    <div class="step-topline"><span class="step-index">۴</span><div><div class="eyebrow">تنظیمات</div><h2>فقط چیزهایی را تنظیم کن که لازم داری</h2><p>موارد حرفه‌ای پایین صفحه قرار گرفته‌اند تا مسیر ساخت شلوغ نشود.</p></div></div>
    <div class="quick-settings-grid">
        <div class="quick-setting"><span>🎯</span><div><strong>تطبیق</strong><small>هر واژه یا همه واژه‌ها</small></div><select name="match_mode"><option value="any">هر واژه</option><option value="all">همه واژه‌ها</option><option value="contains">شامل عبارت</option><option value="exact">تطبیق دقیق</option><option value="word">تطبیق کلمه</option></select></div>
        <div class="quick-setting"><span>⏱</span><div><strong>وقفه پاسخ</strong><small>برای جلوگیری از پاسخ تکراری</small></div><input name="cooldown_seconds" type="number" min="0" max="86400" value="30"></div>
        <div class="quick-setting"><span>👥</span><div><strong>مخاطب</strong><small>تمام مخاطبان یا دنبال‌کننده‌ها</small></div><select name="audience_follower_status"><option value="any">همه</option><option value="follower">دنبال‌کننده</option><option value="non_follower">غیردنبال‌کننده</option></select></div>
    </div>
    <details class="advanced-wrap"><summary><span>تنظیمات پیشرفته</span><small>محدودیت، ساعات کاری، دکمه‌ها، پاسخ‌های سریع و بیشتر</small><b>⌄</b></summary><div class="advanced-body">
        <div class="grid-three"><div class="quick-input-wrap"><label>حداقل دنبال‌کننده</label><input name="min_follower_count" type="number" min="0" placeholder="بدون محدودیت"></div><div class="quick-input-wrap"><label>حداکثر پاسخ روزانه</label><input name="max_per_user_day" type="number" min="1" max="10000" placeholder="بدون محدودیت"></div><div class="quick-input-wrap"><label>اولویت اجرا</label><input name="priority" type="number" min="1" max="9999" value="100"></div></div>
        <div class="grid-three"><div class="quick-input-wrap"><label>تأخیر پیام خصوصی</label><input name="dm_delay_seconds" type="number" min="0" max="86400" value="0"></div><div class="quick-input-wrap"><label>تأخیر پاسخ عمومی</label><input name="comment_reply_delay_seconds" type="number" min="0" max="86400" value="0"></div><div class="quick-input-wrap"><label>وضعیت مخاطب نامشخص</label><select name="audience_unknown"><option value="send">ارسال شود</option><option value="skip">صرف‌نظر شود</option><option value="verify">ابتدا بررسی شود</option></select></div></div>
        <div class="grid-three"><label class="toggle-row"><span><strong>فقط ساعات کاری</strong><small>ساعات را از تنظیمات اصلی می‌خواند.</small></span><input class="toggle-input" type="checkbox" name="business_hours_only" value="1"><span class="toggle-ui"></span></label><label class="toggle-row"><span><strong>تحویل به اپراتور</strong><small>بعد از اجرا برای پیگیری علامت بخورد.</small></span><input class="toggle-input" type="checkbox" name="handoff_enabled" value="1"><span class="toggle-ui"></span></label><label class="toggle-row"><span><strong>ردیابی لینک</strong><small>برای لینک‌های موجود در پاسخ.</small></span><input class="toggle-input" type="checkbox" name="link_tracking" value="1"><span class="toggle-ui"></span></label></div>
        <div class="grid-three"><div class="quick-input-wrap"><label>رفتار در دایرکت هم</label><select name="also_match_in_dms"><option value="0">فقط همین محرک</option><option value="1">در پیام مستقیم هم بررسی شود</option></select></div><div class="quick-input-wrap"><label>پیام خارج از بازه</label><select name="message_tag"><option value="">بدون برچسب</option><option value="HUMAN_AGENT">برچسب پیگیری انسانی</option></select></div><label class="toggle-row"><span><strong>پیش‌نمایش لینک</strong><small>در صورت پشتیبانی فعال باشد.</small></span><input class="toggle-input" type="checkbox" name="link_preview" value="1" checked><span class="toggle-ui"></span></label></div>
        <div class="form-field full"><label>پاسخ‌های جایگزین <span class="muted">هر خط یک نسخه</span></label><textarea name="variations" placeholder="سلام، در خدمتم…
سلام! پیام شما رسید…"></textarea></div>
        <div class="form-field full"><label>پاسخ‌های سریع</label><div class="repeater" data-repeater="quick"></div><button type="button" class="secondary-action" data-repeater-add="quick">＋ افزودن پاسخ سریع</button></div>
        @include('partials.automation_button_builder', ['builderId'=>'createButtonBuilder','buttons'=>[],'accountId'=>''])
        <div class="form-grid"><div class="form-field"><label>قالب کارت / چرخان <span class="muted">اختیاری</span></label><textarea id="templateBuilder" placeholder='برای کارت یا چرخان، JSON قالب را وارد کن.'></textarea></div><div class="form-field"><label>کلمات استثنا</label><textarea name="excluded_keywords" placeholder="کلمه‌هایی که نباید پاسخ بگیرند…"></textarea></div></div>
        <label class="toggle-row"><span><strong>مخفی‌کردن کامنت پس از تشخیص</strong><small>فقط برای محرک کامنت.</small></span><input class="toggle-input" type="checkbox" name="hide_comment" value="1"><span class="toggle-ui"></span></label>
        <div class="form-field"><label>برچسب مخاطب</label><input name="add_tag" placeholder="مثلاً مشتری جدید"></div>
    </div></details>
    <div class="final-review"><div class="review-flow"><span id="reviewTrigger">نظر روی پست</span><b>→</b><span id="reviewTarget">همه محتوا</span><b>→</b><span id="reviewAction">پاسخ خصوصی</span></div><div><strong>با ذخیره، اتوماسیون فعال می‌شود.</strong><small>بعداً هر بخش را می‌توانی با یک کلیک ویرایش کنی.</small></div></div>
    <div class="step-actions"><button type="button" class="ghost-action" data-prev-step="3">→ مرحله قبل</button><span>مرحله ۴ از ۴</span><button type="submit" class="primary-action">✓ ذخیره و فعال‌سازی</button></div>
</section>
</div>

<aside class="studio-side sticky-side">
    <section class="studio-preview-card"><div class="studio-preview-head"><div><span class="eyebrow">پیش‌نمایش</span><strong>پیام نهایی</strong></div><span class="live-pill"><i></i> زنده</span></div>
        <div class="phone-frame modern-phone"><div class="phone-screen"><div class="phone-bar"><span></span><b>پیام‌ها</b><span>⋮</span></div><div class="chat-profile"><div class="avatar">IG</div><div><strong>صفحه شما</strong><small>فعال</small></div></div><div class="chat-area"><div class="bubble incoming" id="previewIncoming">قیمت این محصول چنده؟</div><div class="bubble outgoing" id="previewReply">سلام @{{username}} 👋
اطلاعات کامل براتون ارسال شد.</div></div><div class="chat-input">پیام… <span>⌁</span></div></div></div>
    </section>
    <section class="flow-summary-card"><div class="flow-card-title"><strong>مسیر اتوماسیون</strong><span>۳ گام</span></div><div class="visual-flow"><div class="visual-flow-node"><span>۱</span><strong id="flowTrigger">نظر روی پست</strong><small>محرک</small></div><div class="visual-flow-line"></div><div class="visual-flow-node"><span>۲</span><strong id="flowTarget">همه محتوا</strong><small>هدف</small></div><div class="visual-flow-line"></div><div class="visual-flow-node"><span>۳</span><strong id="flowAction">پاسخ خصوصی</strong><small>اقدام</small></div></div></section>
</aside>
</div>
</form>
@endsection
