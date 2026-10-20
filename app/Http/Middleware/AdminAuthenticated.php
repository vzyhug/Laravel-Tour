<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('employee_id')) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập trang quản trị.');
        }

        return $next($request);
    }
}
