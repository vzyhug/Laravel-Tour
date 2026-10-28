<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('customer_id')) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập để tiếp tục.');
        }

        $customer = Customer::find(session('customer_id'));
        if (! $customer || ! $customer->is_active || (int) session('customer_session_version', 0) !== (int) $customer->session_version) {
            $request->session()->forget(['customer_id', 'customer_name', 'customer_session_version']);
            $request->session()->regenerate();

            return redirect()->route('login')->with('error', 'Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.');
        }

        return $next($request);
    }
}
