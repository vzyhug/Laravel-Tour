@extends('layouts.app')
@section('title', 'Tour yêu thích · VietGo')
@section('content')
    <section class="border-b bg-white py-12">
        <div class="container-page">
            <p class="font-semibold text-brand-700">Tài khoản</p>
            <h1 class="mt-1 text-4xl font-black text-ink">Tour yêu thích</h1>
        </div>
    </section>
    <section class="container-page py-10">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($tours as $tour)
                <x-tour-card :tour="$tour" />
            @empty
                <div class="card col-span-full p-12 text-center">
                    <h2 class="text-xl font-bold">Bạn chưa lưu tour nào</h2>
                    <p class="mt-2 text-slate-500">Bấm vào biểu tượng tim ở thẻ tour để lưu lại xem sau.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
