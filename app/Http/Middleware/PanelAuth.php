<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class PanelAuth { public function handle(Request $request,Closure $next):Response {if(!$request->session()->get('panel_authenticated'))return redirect()->route('login');return $next($request);} }
