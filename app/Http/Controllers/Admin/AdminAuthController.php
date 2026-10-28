<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['employee_id', 'employee_name', 'employee_role']);
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Đăng xuất thành công.');
    }
}
