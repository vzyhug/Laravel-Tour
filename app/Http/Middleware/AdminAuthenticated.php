<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticated
{
    private const IDLE_LIMIT = 15; // giây, muốn đổi thời gian thì sửa số này

    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('employee_id')) {
            session()->forget('admin_last_seen');

            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập trang quản trị.');
        }

        // Bấm đăng xuất thủ công: xóa mốc thời gian để lần đăng nhập sau không bị tính nhầm
        if ($request->routeIs('admin.logout')) {
            session()->forget('admin_last_seen');

            return $next($request);
        }

        $last = session('admin_last_seen');
        if ($last !== null && time() - $last > self::IDLE_LIMIT) {
            session()->forget(['employee_id', 'employee_role', 'employee_name', 'admin_last_seen']);

            return redirect()->route('login')->with('error', 'Phiên làm việc đã hết hạn, vui lòng đăng nhập lại.');
        }

        session(['admin_last_seen' => time()]);

        return $next($request);
    }
}
