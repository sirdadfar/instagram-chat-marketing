@php
    $prefix = $prefix ?? 'dm';
    $existingUrl = $attachmentUrl ?? '';
    $existingType = $attachmentType ?? '';
    $existingName = $attachmentName ?? '';
    $existingSequence = $sequence ?? [];
@endphp
<div class="message-attachment-composer" data-message-composer data-prefix="{{ $prefix }}" data-automation-trigger="{{ $automationTrigger ?? '' }}" data-upload-url="{{ route('automations.media.upload') }}">
    <input type="hidden" name="{{ $prefix }}_attachment_url" data-composer-url value="{{ $existingUrl }}">
    <input type="hidden" name="{{ $prefix }}_attachment_type" data-composer-type value="{{ $existingType }}">
    <input type="hidden" name="{{ $prefix }}_attachment_name" data-composer-name value="{{ $existingName }}">
    <input type="hidden" name="{{ $prefix }}_sequence_json" data-composer-sequence value="{{ e(json_encode($existingSequence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}">
    <div class="composer-sequence-head"><strong>صف پیام‌ها</strong><span>چند پیام را پشت‌سرهم بچین؛ هر آیتم یک ارسال مستقل است.</span></div>
    <div class="composer-sequence-list" data-composer-sequence-list></div>
    <div class="composer-sequence-actions">
        <button type="button" class="secondary-action" data-composer-add-text>＋ افزودن پیام متنی</button>
        <button type="button" class="secondary-action" data-composer-add-current>＋ افزودن آیتم فعلی</button>
    </div>
    <div class="composer-capability-head"><div><strong>پیوست پیام</strong><span>متن را با عکس، ویدیو یا پیام صوتی همراه کن.</span></div><span class="composer-size-badge">حداکثر ۲۵ مگابایت</span></div>
    <div class="composer-tabs" role="tablist">
        <button type="button" class="composer-tab is-active" data-composer-tab="text">متن</button>
        <button type="button" class="composer-tab" data-composer-tab="media">عکس و ویدیو</button>
        <button type="button" class="composer-tab" data-composer-tab="voice">پیام صوتی</button>
    </div>
    <div class="composer-panel" data-composer-panel="text"><div class="composer-tip">نام مخاطب، متن ورودی و سایر متغیرها را می‌توانی در پیام استفاده کنی.</div></div>
    <div class="composer-panel" data-composer-panel="media" hidden>
        <input type="file" data-composer-file accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/avi,video/webm" hidden>
        <button type="button" class="composer-upload-button" data-composer-pick><span class="composer-upload-icon">↑</span><span><strong>انتخاب فایل</strong><small>JPG، PNG، WEBP، MP4، MOV، WEBM</small></span></button>
    </div>
    <div class="composer-panel" data-composer-panel="voice" hidden>
        <div class="voice-recorder-card"><div class="voice-recorder-left"><div class="voice-orb" data-composer-orb>●</div><div><strong data-composer-timer>۰۰:۰۰</strong><small>حداکثر ۶۰ ثانیه</small></div></div><div class="voice-recorder-actions"><button type="button" class="voice-record-button" data-composer-record>شروع ضبط</button><button type="button" class="secondary-action" data-composer-stop disabled>توقف</button></div></div>
        <div class="composer-tip">ضبط مرورگر به فرمت WAV تبدیل و برای ارسال آماده می‌شود.</div>
    </div>
    <div class="composer-upload-status" data-composer-status hidden></div>
    <div class="composer-attachment-preview" data-composer-preview hidden><div class="composer-preview-media" data-composer-preview-media></div><div class="composer-preview-icon" data-composer-preview-icon>▧</div><div class="composer-preview-copy"><strong data-composer-preview-name></strong><span data-composer-preview-type></span></div><button type="button" class="icon-button" data-composer-clear title="حذف پیوست">×</button></div>
    <div class="composer-disabled-note" data-composer-disabled hidden><strong>در این پاسخ، پیوست ارسال نمی‌شود.</strong><span>برای پاسخ خصوصی به کامنت، از متن و دکمه یا پاسخ سریع استفاده کن.</span></div>
</div>
