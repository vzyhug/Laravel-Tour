<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $employee = Employee::with('role')->where('username', $data['username'])->first();
        if (! $employee || ! $employee->is_active || ! Hash::check($data['password'], $employee->password_hash)) {
            return back()->withErrors(['username' => 'Tài khoản hoặc mật khẩu không đúng.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        session(['employee_id' => $employee->getKey(), 'employee_name' => $employee->full_name, 'employee_role' => $employee->role->role_name]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['employee_id', 'employee_name', 'employee_role']);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
