<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\CustomerValidation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CustomerPasswordController extends Controller
{
    public function request()
    {
        return view('auth.recovery.forgot');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:100']]);
        $data['email'] = mb_strtolower($data['email']);
        try {
            Password::broker('customers')->sendResetLink([...$data, 'is_active' => true]);
        } catch (TransportExceptionInterface $exception) {
            // Keep transport outages from revealing which addresses belong to customers.
            Log::warning('Customer password reset email could not be delivered.', ['exception_type' => $exception::class]);
        }

        return back()->with('success', 'Nếu email thuộc tài khoản đang hoạt động, liên kết đặt lại mật khẩu sẽ được gửi.');
    }

    public function resetForm(Request $request, string $token)
    {
        $data = $request->validate(['email' => ['nullable', 'string', 'email', 'max:100']]);

        return view('auth.recovery.reset', ['token' => $token, 'email' => $data['email'] ?? '']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'token' => ['required', 'string', 'max:1000'],
            'password' => CustomerValidation::passwordRules(),
        ]);
        $data['email'] = mb_strtolower($data['email']);
        // Lock the customer before validating and consuming the token, so parallel resets cannot reuse it.
        $status = DB::transaction(function () use ($data) {
            $customer = Customer::where('email', $data['email'])->lockForUpdate()->first();
            if (! $customer || ! $customer->is_active) {
                return Password::INVALID_TOKEN;
            }

            return Password::broker('customers')->reset([...$data, 'is_active' => true], function (Customer $customer, string $password) {
                $customer->forceFill(['password_hash' => Hash::make($password), 'session_version' => $customer->session_version + 1])->save();
                event(new PasswordReset($customer));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.']);
        }
        $request->session()->forget(['customer_id', 'customer_name', 'customer_session_version']);
        $request->session()->regenerate();

        return redirect()->route('login')->with('success', 'Đã đặt lại mật khẩu. Vui lòng đăng nhập lại.');
    }

    public function changeForm()
    {
        return view('auth.recovery.change');
    }

    public function change(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:1000'],
            'password' => [...CustomerValidation::passwordRules(), 'different:current_password'],
        ]);
        $version = DB::transaction(function () use ($request, $data) {
            $customer = Customer::whereKey($request->session()->get('customer_id'))->lockForUpdate()->firstOrFail();
            if (! $customer->is_active || (int) $customer->session_version !== (int) $request->session()->get('customer_session_version', 0)) {
                abort(403);
            }
            if (! Hash::check($data['current_password'], $customer->password_hash)) {
                throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
            }
            $customer->forceFill(['password_hash' => Hash::make($data['password']), 'session_version' => $customer->session_version + 1])->save();
            Password::broker('customers')->deleteToken($customer);

            return $customer->session_version;
        });
        $request->session()->regenerate();
        $request->session()->put('customer_session_version', $version);

        return back()->with('success', 'Đã đổi mật khẩu và đăng xuất các phiên khác.');
    }
}
