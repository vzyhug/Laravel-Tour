@extends('layouts.admin')

@section('title', $employee->exists ? 'Sửa nhân viên' : 'Thêm nhân viên')

@section('content')
    <a class="font-semibold text-brand-700" href="{{ route('admin.employees.index') }}">
        ← Danh sách nhân viên
    </a>

    <h1 class="mt-3 text-3xl font-black text-ink">
        {{ $employee->exists ? 'Chỉnh sửa nhân viên' : 'Thêm nhân viên' }}
    </h1>

    <form class="card mt-7 grid gap-5 p-6 md:grid-cols-2" method="POST"
          action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}">
        @csrf
        @if($employee->exists)
            @method('PUT')
        @endif

        <label class="md:col-span-2">
            <span class="label">Họ và tên</span>
            <input class="field @error('full_name') border-red-500 @enderror" name="full_name" value="{{ old('full_name', $employee->full_name) }}" required>
            @error('full_name') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
        </label>

        <label>
            <span class="label">Tên đăng nhập</span>
            <input class="field @error('username') border-red-500 @enderror" name="username" value="{{ old('username', $employee->username) }}" required>
            @error('username') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
        </label>

        <label>
            <span class="label">Email</span>
            <input class="field @error('email') border-red-500 @enderror" type="email" name="email" value="{{ old('email', $employee->email) }}" required>
            @error('email') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
        </label>

        <label>
            <span class="label">Số điện thoại</span>
            <input class="field @error('phone') border-red-500 @enderror" name="phone" value="{{ old('phone', $employee->phone) }}">
            @error('phone') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
        </label>

        <label>
            <span class="label">Vai trò</span>
            <select class="field @error('role_id') border-red-500 @enderror" name="role_id">
                @foreach($roles as $role)
                    <option value="{{ $role->getKey() }}"
                        @selected(old('role_id', $employee->role_id) == $role->getKey())>
                        {{ \App\Support\UiText::role($role->role_name) }}
                    </option>
                @endforeach
            </select>
            @error('role_id') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
        </label>

        <label>
            <span class="label">
                Mật khẩu {{ $employee->exists ? '(để trống nếu không đổi)' : '' }}
            </span>
            <div style="position: relative;">
                <input id="password" class="field @error('password') border-red-500 @enderror" style="padding-right: 44px;" type="password" name="password"
                    @required(!$employee->exists)>
                <button type="button" id="togglePassword"
                        style="position: absolute; top: 50%; right: 12px; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #64748b; display: flex; align-items: center;"
                        aria-label="Hiện/ẩn mật khẩu">
                    <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
            @error('password') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
        </label>

        <label>
            <span class="label">
                Xác nhận mật khẩu
            </span>
            <input class="field" type="password" name="password_confirmation" @required(!$employee->exists)>
        </label>

        <label class="flex items-center gap-3 self-end rounded-xl bg-slate-50 p-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $employee->is_active))>
            <span class="font-semibold">Tài khoản hoạt động</span>
        </label>

        <div class="md:col-span-2">
            <button class="btn-primary">Lưu nhân viên</button>
        </div>
    </form>

    <script>
        const pwInput = document.getElementById('password');
        const eyeOpen = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        document.getElementById('togglePassword').addEventListener('click', function () {
            const show = pwInput.type === 'password';
            pwInput.type = show ? 'text' : 'password';
            eyeOpen.style.display = show ? 'none' : 'block';
            eyeClosed.style.display = show ? 'block' : 'none';
        });
    </script>
@endsection
