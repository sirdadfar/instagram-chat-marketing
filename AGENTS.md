# AGENTS.md — قواعد پروژه

## قواعد اجباری (همیشه اجرا شود)
- در پایان هر تغییر در هر چت، تغییرات باید با `git commit` ثبت شوند. هیچ جلسه‌ای را با تغییرات کامیت‌نشده تمام نکن.
- پیام کامیت باید از نوع Conventional Commit باشد و خلاصه‌ی کوتاه و مشخصی از تغییر را داشته باشد (مثلاً `Message queue: reorder + delegation fixes`).

## نکات پروژه
- فرانت‌اند: Blade + `public/js/panel.js` + `public/css/panel.css` (بدون build step).
- این پروژه به فارسی (RTL) است؛ متن‌های رابط کاربری فارسی و اعداد با `toFa` فارسی شوند.
- تغییر در کامپوننت‌هایی مثل «صف پیام‌ها» (message_attachment_composer) باید هم در صفحه‌ی create و هم edit تست شود.
