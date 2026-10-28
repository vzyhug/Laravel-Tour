@php
    $alertIsError = session('error') || $errors->any();
    $alertMessages = collect([session('success'), session('error')])
        ->filter(fn ($message) => filled($message))
        ->merge($errors->all())
        ->values();
@endphp

@if($alertMessages->isNotEmpty())
    <dialog data-flash-alert class="flash-alert" aria-labelledby="flash-alert-title" aria-describedby="flash-alert-message" @if($alertIsError) role="alertdialog" @endif>
        <div class="flash-alert__topline">
            <span class="flash-alert__icon {{ $alertIsError ? 'flash-alert__icon--error' : 'flash-alert__icon--success' }}" aria-hidden="true">
                @if($alertIsError)
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.74 3h14.92a2 2 0 0 0 1.74-3l-7.46-13.1a2 2 0 0 0-3.48 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
            </span>
            <button type="button" class="flash-alert__close" data-flash-close aria-label="Đóng thông báo">×</button>
        </div>

        <p class="flash-alert__eyebrow">{{ $alertIsError ? 'VietGo · Cần chú ý' : 'VietGo · Thông báo' }}</p>
        <h2 id="flash-alert-title" class="flash-alert__title">{{ $alertIsError ? 'Chưa thể hoàn tất' : 'Thành công' }}</h2>
        <div id="flash-alert-message" class="flash-alert__message">
            @foreach($alertMessages as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>

        <button type="button" class="flash-alert__confirm" data-flash-close autofocus>Đã hiểu</button>
    </dialog>
@endif
