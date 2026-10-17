<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f4eff2" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#141016" media="(prefers-color-scheme: dark)">
    <title>خطای داخلی سرور | مدیریت اینستاگرام</title>
    <style>
        :root{color-scheme:light;--bg:#f4eff2;--surface:#fff;--ink:#2b2329;--muted:#6d626b;--subtle:#9b8f97;--line:#eae2e8;
              --accent:#d2356c;--accent-2:#8a3ab4;--grad:linear-gradient(135deg,#d94f86,#8a43b8);
              --danger:#c22b48;--danger-soft:#fde8ec;
              --elev:0 6px 14px rgba(43,29,38,.08),0 30px 70px rgba(43,29,38,.14);--inset:inset 0 1px 0 rgba(255,255,255,.85)}
        [data-theme="dark"]{color-scheme:dark;--bg:#141016;--surface:#1e1923;--ink:#f3edf2;--muted:#b3a8b0;--subtle:#8b7f88;--line:#332b3a;
              --danger:#f2708a;--danger-soft:#3a2330;
              --elev:0 8px 18px rgba(0,0,0,.34),0 34px 80px rgba(0,0,0,.46);--inset:inset 0 1px 0 rgba(255,255,255,.04)}
        *{box-sizing:border-box}
        body{
            margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
            background:
              radial-gradient(1000px 460px at 85% -10%,rgba(224,60,125,.07),transparent 62%),
              radial-gradient(900px 420px at 5% 0%,rgba(138,59,184,.06),transparent 60%),var(--bg);
            color:var(--ink);font-family:Vazirmatn,Tahoma,Arial,sans-serif;line-height:1.75;
        }
        .error-card{
            width:min(100%,600px);padding:clamp(26px,6vw,52px);text-align:center;
            border:1px solid var(--line);border-radius:28px;background:var(--surface);
            box-shadow:var(--elev),var(--inset);
        }
        .brand{display:inline-flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;font-weight:800;margin-bottom:30px}
        .brand-mark{
            display:grid;place-items:center;width:36px;height:36px;border-radius:12px;color:#fff;
            background:var(--grad);font-size:19px;box-shadow:0 8px 18px rgba(210,53,108,.32)
        }
        .code{
            font-size:clamp(76px,18vw,124px);line-height:1;font-weight:900;letter-spacing:-5px;margin:0 0 18px;
            background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent;opacity:.28
        }
        .alert-icon{
            display:grid;place-items:center;width:58px;height:58px;margin:-8px auto 18px;border-radius:19px;
            background:var(--danger-soft);color:var(--danger);font-size:26px;font-weight:900;
            border:1px solid color-mix(in srgb,var(--danger) 25%,transparent)
        }
        h1{font-size:clamp(21px,4vw,26px);margin:0 0 12px;font-weight:800;letter-spacing:-.4px}
        p{margin:0 auto;color:var(--muted);font-size:14px;line-height:2;max-width:420px}
        .hint{
            margin:24px 0;padding:14px 16px;font-size:12.5px;line-height:1.9;text-align:right;
            border:1px dashed var(--line);border-radius:14px;background:color-mix(in srgb,var(--surface) 60%,transparent);color:var(--muted)
        }
        .actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:24px}
        .button{
            display:inline-flex;justify-content:center;align-items:center;min-height:46px;padding:0 20px;
            border-radius:13px;color:var(--ink);text-decoration:none;font:inherit;font-size:13.5px;font-weight:800;
            background:var(--surface);border:1px solid var(--line);
            transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,filter .18s ease;
        }
        .button:hover{transform:translateY(-1px);border-color:color-mix(in srgb,var(--accent) 35%,transparent)}
        .button-primary{background:var(--grad);color:#fff;border:0;box-shadow:0 10px 24px rgba(210,53,108,.3),var(--inset)}
        .button-primary:hover{filter:brightness(1.03)}
        .reference{margin-top:26px;font-size:11px;color:var(--subtle)}
        ::selection{background:rgba(210,53,108,.22)}
        .button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
        @media (max-width:480px){.error-card{border-radius:22px}.brand{margin-bottom:24px}.hint{font-size:12px}}
    </style>
</head>
<body>
    <main class="error-card" role="main">
        <div class="brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><rect x="4" y="4" width="16" height="16" rx="4"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/></svg></span><span>مرکز مدیریت اینستاگرام</span></div>
        <div class="code" aria-hidden="true">500</div>
        <div class="alert-icon" aria-hidden="true">!</div>
        <h1>مشکلی در سمت سرور پیش آمده</h1>
        <p>در حال حاضر امکان تکمیل درخواست وجود ندارد. این خطا معمولاً موقتی است؛ لطفاً چند لحظه دیگر دوباره تلاش کن.</p>
        <div class="hint">اطلاعات فنی خطا برای حفظ امنیت نمایش داده نمی‌شود. اگر مشکل ادامه داشت، گزارش خطای سرور را بررسی کن.</div>
        <div class="actions">
            <a class="button button-primary" href="javascript:window.location.reload()">تلاش دوباره</a>
            <a class="button" href="/login">بازگشت به ورود</a>
        </div>
        <div class="reference">خطای داخلی سرور · HTTP 500</div>
    </main>
</body>
</html>
