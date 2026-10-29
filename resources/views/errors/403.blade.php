<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Không có quyền truy cập · VietGo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="travel-site min-h-screen bg-sand text-ink">
    <main class="flex min-h-screen items-center justify-center p-6">
        <div class="card w-full max-w-lg rounded-3xl p-8 text-center sm:p-12">
            <p class="eyebrow">VietGo · Giới hạn truy cập</p>
            <p class="mt-5 font-serif text-8xl text-brand-600">403</p>

            <h1 class="mt-4 font-serif text-3xl text-ink">
                Bạn không có quyền truy cập
            </h1>

            <p class="mt-3 text-slate-600">
                {{ $exception->getMessage() ?: 'Tài khoản của bạn không được phép sử dụng chức năng này.' }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Nếu cần dùng chức năng này, vui lòng liên hệ quản trị viên để được cấp quyền.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="javascript:history.back()"
                   class="btn-secondary">
                    ← Quay lại
                </a>
                <a href="{{ route('home') }}" class="btn-primary">Về trang chủ</a>
            </div>
        </div>
    </main>
</body>
</html>
