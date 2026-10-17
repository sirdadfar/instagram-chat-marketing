@extends('layout')
@section('page_title','قالب پیام')
@section('page_hint','متن‌های آماده و متغیرهای قابل استفاده در Automation')
@section('content')
<div class="page-header-row"><div><div class="eyebrow">کتابخانه قالب‌ها</div><h1>کتابخانه پیام</h1><p>پیام‌های آماده بساز و در مسیرهای گفتگو دوباره استفاده کن.</p></div></div>
<div class="studio-grid">
<section class="panel-card">
<div class="section-head"><div><div class="eyebrow">قالب جدید</div><h2>ساخت قالب</h2></div></div>
<form method="POST" action="{{ route('templates.store') }}" class="form-grid">@csrf
<div class="form-field"><label>نام</label><input name="name" required placeholder="مثلاً پاسخ قیمت"></div>
<div class="form-field"><label>دسته</label><select name="category"><option value="general">عمومی</option><option value="sales">فروش</option><option value="support">پشتیبانی</option><option value="follow_gate">شرط دنبال‌کردن</option></select></div>
<div class="form-field full"><label>متن پیام</label><textarea name="body" rows="7" required placeholder="سلام @{{username}} 👋&#10;اطلاعات محصول: @{{product_price}}"></textarea><small class="muted">متغیرها با @{{variable}} در قالب Blade نمایش داده می‌شوند و در پیام به {{variable}} تبدیل می‌شوند.</small></div>
<div class="full"><button class="primary-action" type="submit">＋ ذخیره قالب</button></div>
</form>
</section>
<section class="panel-card"><div class="section-head"><div><div class="eyebrow">متغیرها</div><h2>متغیرهای آماده</h2></div></div>
<div class="tag-cloud">@foreach(['username','first_name','comment','post_url','post_title','product_price','current_date','current_time'] as $v)<span class="soft-tag">&#123;&#123;{{ $v }}&#125;&#125;</span>@endforeach</div></section>
</div>
<div class="automation-grid">
@forelse($templates as $template)
@php
    $templateVariables = is_array($template->variables) ? $template->variables : [];
    $templateCategories = ['general' => 'عمومی', 'sales' => 'فروش', 'support' => 'پشتیبانی', 'follow_gate' => 'بررسی فالو'];
@endphp
<article class="automation-card automation-card-premium"><div class="automation-card-top"><div><strong>{{ $template->name }}</strong><span>{{ $templateCategories[$template->category] ?? $template->category }} · {{ $template->active ? 'فعال' : 'متوقف' }}</span></div>
<div class="card-menu"><form method="POST" action="{{ route('templates.toggle',$template) }}">@csrf<button class="quick-icon" title="تغییر وضعیت">{{ $template->active ? 'Ⅱ' : '▶' }}</button></form><form method="POST" action="{{ route('templates.destroy',$template) }}">@csrf @method('DELETE')<button class="quick-icon danger-soft">×</button></form></div></div>
<div class="panel-card"><p style="white-space:pre-wrap">{{ $template->body }}</p></div><div class="automation-card-footer"><div>@foreach($templateVariables as $v)<span class="soft-tag">{{ $v }}</span>@endforeach</div></div></article>
@empty<div class="empty-state span-full"><strong>هنوز قالبی ساخته نشده</strong></div>@endforelse
</div>
<div class="pagination-wrap">{{ $templates->links() }}</div>
@endsection

<style>
.template-library-intro{max-width:780px}
.studio-grid{align-items:start;gap:18px}
.studio-grid>.panel-card,.automation-card-premium{border:1px solid rgba(148,163,184,.2);box-shadow:0 10px 28px rgba(15,23,42,.04)}
.automation-card-premium{min-width:0;overflow:hidden}
.automation-card-premium>.panel-card{margin:14px 0;padding:14px;background:rgba(148,163,184,.045);border:1px solid rgba(148,163,184,.17);overflow-wrap:anywhere}
.automation-card-premium>.panel-card p{margin:0;line-height:1.95;overflow-wrap:anywhere}
.automation-card-premium .automation-card-footer{gap:8px}
.automation-card-premium .automation-card-footer>div{display:flex;gap:6px;flex-wrap:wrap}
@media(max-width:700px){.studio-grid{grid-template-columns:minmax(0,1fr);gap:14px}.automation-card-premium{padding:14px}.automation-card-top{gap:10px}.automation-card-top strong{overflow-wrap:anywhere}}
</style>
