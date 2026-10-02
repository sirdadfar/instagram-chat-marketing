@php
    $builderId = $builderId ?? 'buttonBuilder';
    $buttons = is_array($buttons ?? null) ? array_values($buttons) : [];
    $accountId = $accountId ?? '';
@endphp
<div class="button-builder" id="{{ $builderId }}" data-button-builder data-account-id="{{ $accountId }}">
    <div class="button-builder-head">
        <div>
            <strong>دکمه‌های پیام</strong>
            <p>حداکثر ۳ دکمه. برای لینک، آدرس بده؛ برای ادامه گفتگو، یک مسیر انتخاب کن.</p>
        </div>
        <span class="builder-limit" data-button-count>{{ count($buttons) }} / ۳</span>
    </div>

    <div class="button-preview" data-button-preview>
        <div class="button-preview-label">پیش‌نمایش</div>
        <div class="button-preview-card">
            <div class="button-preview-message">سلام، اطلاعات کامل را برایتان فرستادم.</div>
            <div class="button-preview-actions" data-button-preview-actions>
                <span class="button-preview-empty">هنوز دکمه‌ای اضافه نشده است.</span>
            </div>
        </div>
    </div>

    <div class="button-builder-list" data-button-list>
        @foreach($buttons as $index => $button)
            @php
                $type = ($button['type'] ?? '') === 'postback' ? 'postback' : 'url';
                $payload = (string) ($button['payload'] ?? '');
                $workflowId = str_starts_with($payload, 'zernio:workflow:') ? substr($payload, strlen('zernio:workflow:')) : '';
            @endphp
            <div class="button-builder-row" data-button-row data-index="{{ $index }}" data-workflow-id="{{ $workflowId }}">
                <button type="button" class="button-drag" aria-label="جابجایی">⋮⋮</button>
                <span class="button-index" data-button-index>{{ $index + 1 }}</span>
                <div class="button-row-fields">
                    <div class="button-field">
                        <label>عنوان</label>
                        <input type="text" maxlength="20" data-button-title value="{{ $button['title'] ?? '' }}" placeholder="مثلاً مشاهده قیمت">
                        <small>حداکثر ۲۰ کاراکتر</small>
                    </div>
                    <div class="button-field">
                        <label>نوع</label>
                        <select data-button-type>
                            <option value="url" @selected($type === 'url')>باز کردن لینک</option>
                            <option value="postback" @selected($type === 'postback')>ادامه مسیر گفتگو</option>
                        </select>
                    </div>
                    <div class="button-field" data-button-url-wrap @if($type === 'postback') hidden @endif>
                        <label>لینک</label>
                        <input type="url" data-button-url value="{{ $button['url'] ?? '' }}" placeholder="https://example.com">
                    </div>
                    <div class="button-field" data-button-workflow-wrap @if($type !== 'postback') hidden @endif>
                        <label>مسیر گفتگو</label>
                        <select data-button-workflow>
                            <option value="">در حال دریافت مسیرها…</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="button-remove" data-button-remove aria-label="حذف دکمه">×</button>
            </div>
        @endforeach
    </div>

    <button type="button" class="button-add" data-button-add>＋ افزودن دکمه</button>
    <div class="button-builder-note" data-button-note hidden></div>
</div>
