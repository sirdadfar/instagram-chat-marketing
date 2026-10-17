<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f4eff2" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#141016" media="(prefers-color-scheme: dark)"><title>ورود | مدیریت اینستاگرام</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/panel.css') }}"></head><body class="login-page"><div class="login-minimal"><div class="login-art"><div class="art-card art-card-a">✦</div><div class="art-card art-card-b">◎</div><div class="art-card art-card-c">↗</div><div class="art-glow"></div></div><div class="login-form-wrap"><div class="login-card">
<div class="login-brand"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" class="fill"/></svg></span><div><strong>مدیریت اینستاگرام</strong><small>ورود به مرکز عملیات</small></div></div>
<div class="login-title"><h1>خوش آمدی 👋</h1><p>با نام کاربری و رمز عبور حساب خودت وارد شو.</p></div>
@if($errors->any())<div class="flash flash-danger" role="alert"><span class="flash-icon">!</span><div><strong>ورود انجام نشد</strong><div class="auth-error-list">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div></div>@endif
<form method="POST" action="{{ route('login.authenticate') }}" novalidate>@csrf
<div class="form-field"><label for="username">نام کاربری</label><input id="username" name="username" type="text" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="username" required autofocus placeholder="مثلاً ali_r" value="{{ old('username') }}"></div>
<div class="form-field"><label for="password">رمز عبور</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="رمز عبور"><button type="button" class="password-toggle" data-password-toggle aria-label="نمایش/پنهان‌کردن رمز" aria-pressed="false"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
<div class="auth-row"><label class="auth-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> <span>مرا به خاطر بسپار</span></label><a class="auth-forgot" href="{{ route('register') }}">ساخت حساب جدید</a></div>
<button class="primary-action full-width" type="submit">ورود به پنل ↗</button>
</form>
<div class="auth-meta"><strong>مدیریت اینستاگرام</strong><span>ورود شما در بالاترین سطح ممکن رمزنگاری می‌شود</span></div>
</div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var btn=document.querySelector('[data-password-toggle]');
  if(!btn)return;
  var input=document.getElementById('password');
  var on=false;
  var eyeOn='<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path d="m9.9 14.1 4.2-4.2M14.1 14.1 9.9 9.9"/></svg>';
  var eyeOff='<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  btn.addEventListener('click',function(){
    on=!on;
    input.type=on?'text':'password';
    btn.innerHTML=on?eyeOn:eyeOff;
    btn.setAttribute('aria-pressed',on?'true':'false');
    input.focus();
  });
});
</script>
</body></html>
