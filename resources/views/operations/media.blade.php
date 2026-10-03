@extends('layout')
@section('page_title','کتابخانه رسانه')
@section('page_hint','رسانه‌های آماده برای استفاده در Automationها')
@section('content')
<div class="page-header-row"><div><div class="eyebrow">Media Library</div><h1>کتابخانه رسانه</h1><p>لینک رسانه‌های قابل ارسال را نگه‌داری و مدیریت کن.</p></div></div>
<section class="panel-card">
<form method="POST" action="{{ route('media-library.store') }}" class="form-grid">@csrf
<div class="form-field"><label>نام رسانه</label><input name="name" required placeholder="مثلاً ویدیوی معرفی"></div>
<div class="form-field"><label>نوع</label><select name="type"><option value="image">تصویر</option><option value="video">ویدیو</option><option value="audio">صوت</option><option value="file">فایل</option></select></div>
<div class="form-field full"><label>URL عمومی</label><input type="url" name="url" required placeholder="https://..."></div>
<div class="form-field full"><label>توضیح</label><input name="alt_text" placeholder="توضیح کوتاه"></div>
<div class="full"><button class="primary-action">＋ افزودن رسانه</button></div>
</form>
</section>
<div class="automation-grid">
@forelse($media as $item)
<article class="automation-card automation-card-premium">
<div class="automation-card-top"><div><strong>{{ $item->name }}</strong><span>{{ $item->type }}</span></div><form method="POST" action="{{ route('media-library.destroy',$item) }}">@csrf @method('DELETE')<button class="quick-icon danger-soft">×</button></form></div>
@if(in_array($item->type,['image','video']))<div style="margin:12px 0"><img src="{{ $item->url }}" alt="{{ $item->alt_text }}" style="width:100%;max-height:260px;object-fit:cover;border-radius:18px"></div>@endif
<div class="automation-card-footer"><a class="text-action" href="{{ $item->url }}" target="_blank" rel="noopener">باز کردن رسانه ←</a></div>
</article>
@empty<div class="empty-state span-full"><strong>کتابخانه خالی است</strong></div>@endforelse
</div>
<div class="pagination-wrap">{{ $media->links() }}</div>
@endsection
