<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:1'],
            'password' => ['required', 'string', 'min:1'],
        ], [
            'username.required' => 'نام کاربری را وارد کنید.',
            'password.required' => 'رمز عبور را وارد کنید.',
        ]);

        $username = mb_strtolower(trim($data['username']));
        $user = User::where('username', $username)->first();

        // Uniform error → no username enumeration via the login form.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()
                ->withErrors(['username' => 'نام کاربری یا رمز عبور نادرست است.'])
                ->onlyInput('username');
        }

        if ($user->status !== 'active') {
            return back()
                ->withErrors(['username' => 'حساب کاربری شما غیرفعال است. با مدیر سامانه تماس بگیرید.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        Auth::login($user, remember: (bool) $request->boolean('remember'));
        $request->session()->put('panel_authenticated', true);
        $request->session()->put('panel_admin_label', $user->name);

        return redirect()->intended(route('dashboard'));
    }

    public function register(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    /**
     * Open registration. Mitigations: request throttling (route middleware)
     * and strict validation. The very first user ever created becomes admin.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9_.-]+$/i', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:120', 'confirmed'],
        ], [
            'name.required' => 'نام را وارد کنید.',
            'name.min' => 'نام باید حداقل ۲ نویسه باشد.',
            'username.required' => 'نام کاربری را وارد کنید.',
            'username.min' => 'نام کاربری باید حداقل ۳ نویسه باشد.',
            'username.max' => 'نام کاربری حداکثر ۴۰ نویسه است.',
            'username.unique' => 'این نام کاربری قبلاً گرفته شده است.',
            'username.regex' => 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، عدد و _ - . باشد.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'password.min' => 'رمز عبور باید حداقل ۸ نویسه باشد.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'username' => mb_strtolower(trim($data['username'])),
            'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
            'password' => $data['password'],
            'is_admin' => User::count() === 0, // first registrant manages the instance
            'status' => 'active',
            'zernio_api_key_enc' => null,
        ]);

        $request->session()->regenerate();
        Auth::login($user);
        $request->session()->put('panel_authenticated', true);
        $request->session()->put('panel_admin_label', $user->name);

        return redirect()->intended(route('dashboard'))
            ->with('success', 'ثبت‌نام انجام شد؛ خوش آمدید! برای شروع، کلید سرویس را در تنظیمات و پیج خود را اضافه کنید.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
