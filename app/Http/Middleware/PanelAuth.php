<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
class PanelAuth { public function handle(Request $request,Closure $next):Response {
    if(!Auth::check()||$request->session()->get('panel_authenticated')!==true){
        Auth::logout();
        return redirect()->route('login');
    }
    $user=Auth::user();
    if($user->status!=='active'){
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->withErrors(['email'=>'حساب کاربری شما توسط مدیر غیرفعال شده است.']);
    }
    return $next($request);
} }