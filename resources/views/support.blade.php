@extends('layouts.app')
@section('title', 'Trung tâm trợ giúp · VietGo')
@section('content')
<section class="container-page py-12">
    <p class="eyebrow">VietGo · Trợ giúp</p><h1 class="section-title mt-3">Bạn cần hỗ trợ điều gì?</h1>
    <p class="mt-4 max-w-2xl text-sm leading-7 text-muted">VietGo hiện phục vụ luồng tour có lịch khởi hành trong môi trường demo. Các dịch vụ khác chưa mở bán; chưa có kết nối nhà cung cấp hoặc cổng thanh toán thật.</p>
    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_300px]">
        <div class="grid gap-4">
            @foreach([
                ['Làm sao biết tour còn chỗ?', 'Tìm theo ngày khởi hành và số người. Số chỗ được kiểm tra lại khi giữ chỗ; kết quả tìm kiếm chưa phải xác nhận đặt tour.'],
                ['Giá và thời hạn thanh toán được tính thế nào?', 'Giá hiển thị theo người lớn/trẻ em và VND. Tổng tiền dựa trên lịch, số khách và mã giảm giá hợp lệ. Báo giá có thời hạn; sau khi giữ chỗ, hạn thanh toán được lưu trên đơn và không đổi khi tải lại trang.'],
                ['Thanh toán ở đây có thu tiền thật không?', 'Không. Các lựa chọn ngân hàng, thẻ, ví và tiền mặt đều là mô phỏng. Không nhập số thẻ, chuyển khoản hoặc gửi thông tin thanh toán thật. Bạn có thể thử kết quả thành công hoặc thất bại.'],
                ['Mã giảm giá đã sao chép có được áp dụng chưa?', 'Chưa. Nhập mã và bấm Áp dụng ở bước thông tin khách. Hệ thống kiểm tra lại điều kiện và lượt dùng trước khi tạo đơn.'],
                ['Xem chứng từ tour và hóa đơn ở đâu?', 'Đăng nhập và mở Chuyến đi của tôi. Đơn đã thanh toán và còn hiệu lực có chứng từ tour demo; hóa đơn mô phỏng là tài liệu riêng và không phải hóa đơn thuế hợp pháp.'],
                ['Tôi có thể hủy và hoàn tiền không?', 'Mở chi tiết đơn để xem điều kiện đã chốt khi đặt, hạn hủy và số tiền hoàn mô phỏng. Chính sách của đơn mới không thay đổi điều kiện đã lưu của đơn cũ. Đơn hết hạn thanh toán được giải phóng chỗ và không tạo hoàn tiền.'],
                ['Tôi là người đặt nhưng không tham gia tour?', 'Thông tin liên hệ của người đặt được tách khỏi danh sách hành khách. Chỉ tài khoản sở hữu đơn có quyền xem, thanh toán, hủy hoặc in chứng từ.'],
            ] as [$question, $answer])
            <details class="card p-5"><summary class="cursor-pointer text-sm font-bold">{{ $question }}</summary><p class="mt-4 text-sm leading-7 text-muted">{{ $answer }}</p></details>
            @endforeach
        </div>
        <aside class="card h-fit p-6"><h2 class="text-lg">Quản lý chuyến đi</h2><p class="mt-3 text-sm leading-7 text-muted">Kiểm tra trạng thái, hạn thanh toán và chứng từ trong tài khoản. Không chia sẻ giấy tờ hành khách hoặc mã phiên đăng nhập.</p><a class="btn-primary mt-5 w-full" href="{{ route('customer.dashboard') }}">Đặt chỗ của tôi</a><a class="btn-secondary mt-3 w-full" href="{{ route('tours.index') }}">Tìm tour</a></aside>
    </div>
</section>
@endsection
