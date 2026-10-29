@php
    $alertVariant = session('warning') ? 'warning' : ((session('error') || $errors->any()) ? 'error' : 'success');
    $alertMessages = collect([session('warning'), session('error'), session('success')])
        ->filter(fn ($message) => filled($message))
        ->merge($errors->all())
        ->values();
@endphp

@if($alertMessages->isNotEmpty())
    <dialog data-flash-alert class="flash-alert flash-alert--{{ $alertVariant }}" aria-labelledby="flash-alert-title" aria-describedby="flash-alert-message" @if($alertVariant !== 'success') role="alertdialog" @endif>
        <div class="flash-alert__topline">
            <span class="flash-alert__icon" aria-hidden="true">
                @if($alertVariant === 'warning')
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.74 3h14.92a2 2 0 0 0 1.74-3l-7.46-13.1a2 2 0 0 0-3.48 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @elseif($alertVariant === 'error')
                    <svg viewBox="0 0 24 24" fill="none"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
            </span>
            <button type="button" class="flash-alert__close" data-flash-close aria-label="Đóng thông báo">×</button>
        </div>
        <p class="flash-alert__eyebrow">VietGo · {{ ['warning' => 'Lưu ý', 'error' => 'Có lỗi xảy ra', 'success' => 'Thông báo'][$alertVariant] }}</p>
        <h2 id="flash-alert-title" class="flash-alert__title">{{ ['warning' => 'Chưa thể thực hiện', 'error' => 'Cần kiểm tra lại', 'success' => 'Thành công'][$alertVariant] }}</h2>
        <div id="flash-alert-message" class="flash-alert__message">
            @foreach($alertMessages as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
        <button type="button" class="flash-alert__confirm" data-flash-close autofocus>Đã hiểu</button>
    </dialog>
@endif
