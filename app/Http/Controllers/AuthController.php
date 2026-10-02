<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (session('panel_authenticated') === true) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:1'],
        ], [
            'password.required' => 'رمز عبور را وارد کنید.',
        ]);

        $configuredHash = (string) config('panel.auth_password_hash', env('PANEL_ADMIN_PASSWORD_HASH', ''));

        if ($configuredHash === '' || !Hash::check($data['password'], $configuredHash)) {
            return back()
                ->withErrors(['password' => 'رمز عبور صحیح نیست.'])
                ->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('panel_authenticated', true);
        $request->session()->put('panel_admin_label', (string) config('panel.admin_label', 'مدیر پنل'));

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
