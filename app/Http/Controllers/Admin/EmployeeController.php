<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('admin.employees.index', ['employees' => Employee::with('role')->orderByDesc('employee_id')->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.employees.form', ['employee' => new Employee(['is_active' => true]), 'roles' => Role::orderBy('role_name')->get()]);
    }

    public function edit(Employee $employee): View
    {
        return view('admin.employees.form', ['employee' => $employee, 'roles' => Role::orderBy('role_name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEmployee($request);
        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);
        Employee::create($data);

        return redirect()->route('admin.employees.index')->with('success', 'Đã thêm nhân viên.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validateEmployee($request, $employee);
        if (! empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']);
        $employee->update($data);

        return redirect()->route('admin.employees.index')->with('success', 'Đã cập nhật nhân viên.');
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $id = $employee?->getKey();

        return $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('employees', 'username')->ignore($id, 'employee_id')],
            'email' => ['required', 'email', Rule::unique('employees', 'email')->ignore($id, 'employee_id')],
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^(0|\+84)[0-9]{9}$/'],
            'role_id' => ['required', 'exists:roles,role_id'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$employee ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.required' => 'Tên đăng nhập không được để trống.',
            'username.unique' => 'Tên đăng nhập này đã tồn tại.',
            'email.required' => 'Email không được để trống.',
            'email.unique' => 'Email này đã tồn tại.',
            'full_name.required' => 'Họ và tên không được để trống.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (VD: 0901234567).',
            'role_id.required' => 'Vui lòng chọn vai trò.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);
    }
}
