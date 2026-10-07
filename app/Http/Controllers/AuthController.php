<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

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
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $customer = Customer::where('email', $credentials['email'])->first();
        if (! $customer || ! $customer->is_active || ! Hash::check($credentials['password'], $customer->password_hash)) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        session(['customer_id' => $customer->getKey(), 'customer_name' => $customer->full_name]);

        return redirect()->intended(route('home'))->with('success', 'Đăng nhập thành công.');
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
