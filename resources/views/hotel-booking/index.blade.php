@extends('layouts.app')
@section('content')
<section class="container-page py-10 space-y-5"><h1 class="section-title">Đơn khách sạn của tôi</h1>
@forelse($bookings as $booking)<article class="border p-5"><a class="text-link" href="{{ route('hotel-bookings.show', $booking) }}">{{ $booking->code }} · {{ $booking->roomType->hotel->name }}</a><p>{{ $booking->status }} · {{ number_format($booking->total_amount) }} ₫ @if($booking->roomType->is_demo) · DEMO @endif</p></article>@empty<p>Bạn chưa có đơn khách sạn.</p>@endforelse
{{ $bookings->links() }}</section>
@endsection
