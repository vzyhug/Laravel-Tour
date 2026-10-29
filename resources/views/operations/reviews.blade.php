@extends('layouts.admin')
@section('title', 'Đánh giá của khách')
@section('content')
<section class="mx-auto max-w-5xl">
    <p class="admin-page-eyebrow">Phản hồi sau hành trình</p>
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <h1 class="admin-page-title">Đánh giá của khách</h1>
        <span class="rounded-full bg-brand-50 px-4 py-2 text-xs font-semibold text-brand-700">{{ $reviews->total() }} đánh giá</span>
    </div>
    <p class="mt-3 text-sm text-muted">Phản hồi từ khách trên các chuyến bạn phụ trách, mới nhất ở đầu danh sách.</p>
    <div class="mt-7 grid gap-4">
        @forelse($reviews as $review)
            <article class="rounded-2xl border border-[#eadfd4] bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <strong class="text-sm text-ink">{{ $review->customer?->full_name ?? 'Khách hàng' }}</strong>
                    <span class="rounded-full bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700">{{ $review->rating }}/5 sao</span>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-ink">{{ $review->feedback }}</p>
                <div class="mt-4 border-t border-[#eadfd4] pt-4 text-xs text-muted">
                    <a class="font-semibold text-brand-700 hover:underline" href="{{ route('operations.show', $review->booking->departure) }}#departure-reviews">{{ $review->booking->departure->tour->title }}</a>
                    <p class="mt-2">Khởi hành {{ $review->booking->departure->departure_date->format('d/m/Y') }} · Đánh giá ngày {{ $review->created_at?->format('d/m/Y') }}</p>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-[#eadfd4] bg-white p-10 text-center">
                <h2 class="font-serif text-2xl">Chưa có đánh giá</h2>
                <p class="mt-3 text-sm text-muted">Đánh giá sẽ xuất hiện khi khách gửi phản hồi cho chuyến bạn phụ trách.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $reviews->links() }}</div>
</section>
@endsection
