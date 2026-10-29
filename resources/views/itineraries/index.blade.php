@extends('layouts.app')
@section('title', 'Lịch trình của tôi')
@section('content')
<section class="container-page py-10">
    <x-account-navigation/>
    <x-page-intro title="Hành trình mang dấu ấn riêng." eyebrow="Theo cách của bạn · Lịch trình đã lưu" icon="map" description="Những điểm muốn ghé, những ngày muốn đi. Mở lịch trình để tiếp tục sắp xếp hoặc theo dõi yêu cầu tư vấn của bạn."/>
    <div class="mt-7 grid gap-5 sm:grid-cols-2">
    @forelse($plans as $plan)
        <article class="card p-6"><h2 class="font-serif text-2xl">{{ $plan->tour->title }}</h2><p class="mt-3 text-xs text-muted">Phiên bản {{ $plan->version }} · Cập nhật {{ $plan->updated_at->format('d/m/Y H:i') }}</p><a class="btn-secondary mt-4" href="{{ route('tours.show', $plan->tour) }}#itinerary">Xem & chỉnh lịch</a>
            @foreach($plan->consultations as $consultation)<p class="mt-4 text-sm"><a class="text-link" href="{{ route('itinerary-consultations.show', $consultation) }}">Yêu cầu #{{ $consultation->id }} · {{ \App\Models\ItineraryConsultation::STATUS_LABELS[$consultation->status] ?? $consultation->status }}</a></p>@endforeach
        </article>
    @empty<p class="text-sm text-muted">Bạn chưa lưu lịch trình. Mở một tour và chọn “Tùy chỉnh lịch & gửi tư vấn”.</p>@endforelse
    </div>
    <div class="mt-6">{{ $plans->links() }}</div>
</section>
@endsection
