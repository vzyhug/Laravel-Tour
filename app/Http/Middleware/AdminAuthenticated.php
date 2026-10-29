<?php

namespace App\Http\Middleware;

use App\Models\Employee;
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

        $employee = Employee::with('role')->find(session('employee_id'));
        if (! $employee || ! $employee->is_active || ! in_array($employee->role?->role_name, ['Admin', 'Guide'], true)) {
            $request->session()->forget(['employee_id', 'employee_name', 'employee_role']);
            $request->session()->regenerate();

            return redirect()->route('login')->with('error', 'T?i kho?n kh?ng c?n quy?n truy c?p. Vui l?ng ??ng nh?p l?i.');
        }

        session(['employee_role' => $employee->role->role_name, 'employee_name' => $employee->full_name]);

        return $next($request);
    }
}
