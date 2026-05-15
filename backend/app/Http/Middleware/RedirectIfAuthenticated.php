<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class RedirectIfAuthenticated {
    public function handle(Request $request, Closure $next, string ...$guards): mixed {
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return response()->json(['message' => 'Đã đăng nhập.'], 200);
            }
        }
        return $next($request);
    }
}
