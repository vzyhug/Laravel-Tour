@extends('layouts.admin')
@section('title', 'Tư vấn lịch trình')
@section('content')
<h1 class="text-3xl font-black">Yêu cầu tư vấn lịch trình</h1>
<form method="GET" class="mt-5 flex gap-3"><select class="field max-w-xs" name="status"><option value="">Tất cả</option>@foreach(\App\Models\ItineraryConsultation::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><button class="btn-primary">Lọc</button></form>
<div class="card mt-6 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-4">Yêu cầu / Tour</th><th class="p-4">Liên hệ</th><th class="p-4">Ngày đi / Nhóm</th><th class="p-4">Trạng thái</th></tr></thead><tbody>
@forelse($consultations as $consultation)<tr class="border-t"><td class="p-4"><a class="text-link" href="{{ route('admin.itinerary-consultations.show', $consultation) }}">#{{ $consultation->id }} · {{ $consultation->snapshot['tour'] }}</a><p class="mt-2 text-xs">Lịch phiên bản {{ $consultation->itinerary_version }}</p></td><td class="p-4">{{ $consultation->contact_name }}<br>{{ $consultation->contact_phone }}</td><td class="p-4">{{ $consultation->travel_date->format('d/m/Y') }}<br>{{ $consultation->adults + $consultation->children }} khách</td><td class="p-4">{{ \App\Models\ItineraryConsultation::STATUS_LABELS[$consultation->status] ?? $consultation->status }}</td></tr>
@empty<tr><td class="p-6" colspan="4">Chưa có yêu cầu.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $consultations->links() }}</div>
@endsection
