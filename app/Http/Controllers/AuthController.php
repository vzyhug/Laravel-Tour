<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use App\Models\Employee;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

        public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string']]);

        $employee = Employee::with('role')
            ->where('username', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();
        if ($employee && $employee->is_active && Hash::check($data['password'], $employee->password_hash)) {
            $request->session()->regenerate();
            session(['employee_id' => $employee->getKey(), 'employee_name' => $employee->full_name, 'employee_role' => $employee->role->role_name]);

            return redirect()->route('admin.dashboard');
        }

        $customer = Customer::where('email', $data['login'])->first();
        if ($customer && $customer->is_active && Hash::check($data['password'], $customer->password_hash)) {
            $request->session()->regenerate();
            session(['customer_id' => $customer->getKey(), 'customer_name' => $customer->full_name]);

            return redirect()->intended(route('home'))->with('success', 'Đăng nhập thành công.');
        }

        return back()->withErrors(['login' => 'Tài khoản hoặc mật khẩu không đúng.'])->onlyInput('login');
    }

    public function register(RegisterCustomerRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('password_confirmation');
        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);
        $customer = Customer::create($data);
        $request->session()->regenerate();
        session(['customer_id' => $customer->getKey(), 'customer_name' => $customer->full_name]);

        return redirect()->route('home')->with('success', 'Tạo tài khoản thành công.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['customer_id', 'customer_name']);
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
