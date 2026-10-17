<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

/** Admin-only: user management and platform supervision. */
class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->withCount('instagramAccounts')
            ->latest()
            ->paginate(20);

        $counts = [
            'users' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'admins' => User::where('is_admin', true)->count(),
            'pages' => InstagramAccount::count(),
        ];

        $pages = InstagramAccount::with('user:id,name,email')
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        return view('admin.users', [
            'users' => $users,
            'counts' => $counts,
            'pages' => $pages,
        ]);
    }

    /** Re-assign an Instagram page to another user (null → unowned). */
    public function assignAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:instagram_accounts,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        InstagramAccount::whereKey($data['account_id'])->update(['user_id' => $data['user_id']]);

        return back()->with('success', 'مالکیت پیج به‌روزرسانی شد.');
    }

    public function suspend(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'حساب خودتان را نمی‌توانید غیرفعال کنید.']);
        }

        $user->update(['status' => 'suspended']);

        return back()->with('success', 'کاربر غیرفعال شد.');
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['status' => 'active']);

        return back()->with('success', 'کاربر فعال شد.');
    }

    public function toggleAdmin(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'نقش خودتان را نمی‌توانید تغییر دهید.']);
        }

        $user->update(['is_admin' => ! $user->is_admin]);

        return back()->with('success', $user->is_admin ? 'کاربر مدیر شد.' : 'دسترسی مدیریتی کاربر حذف شد.');
    }

    /** Generate a new random password for a user and show it once. */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $plain = Str::password(14, symbols: true);

        $user->update(['password' => $plain]);

        $request->session()->now('reset_password_plain', $plain);

        return back()->with('success', 'رمز عبور جدید ساخته شد؛ فقط همین یک بار نمایش داده می‌شود: ')
            ->with('reset_password_plain', $plain);
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'حساب خودتان را نمی‌توانید حذف کنید.']);
        }

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['user' => 'حداقل یک مدیر باید در سامانه باقی بماند.']);
        }

        // Pages go back to the admin pool instead of disappearing with the user.
        InstagramAccount::where('user_id', $user->id)->update(['user_id' => null]);
        $user->delete();

        return back()->with('success', 'کاربر حذف شد؛ پیج‌های او بی‌مالک شدند (قابل واگذاری از همین صفحه).');
    }
}
