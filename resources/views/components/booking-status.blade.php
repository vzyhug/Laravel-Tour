@props(['status'])
@php
    $labels = ['pending' => 'Chờ thanh toán', 'confirmed' => 'Đã xác nhận', 'completed' => 'Đã hoàn thành', 'cancelled' => 'Đã hủy'];
@endphp
<span @class(['badge', 'bg-[#d1e8da]/70 text-sage' => in_array($status, ['confirmed', 'completed']), 'bg-linen text-muted' => $status === 'cancelled'])>{{ $labels[$status] ?? $status }}</span>
