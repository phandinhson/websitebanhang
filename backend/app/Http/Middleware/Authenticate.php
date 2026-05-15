<?php
namespace App\Http\Middleware;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
class Authenticate extends Middleware {
    protected function redirectTo(Request $request): ?string {
        return $request->expectsJson() ? null : null;
    }
    protected function unauthenticated($request, array $guards) {
        abort(response()->json(['success' => false, 'message' => 'Chưa xác thực.'], 401));
    }
}
