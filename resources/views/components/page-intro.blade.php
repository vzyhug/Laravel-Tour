@props(['eyebrow' => 'VietGo · Theo cách của bạn', 'title', 'description' => '', 'icon' => 'map'])
<div class="relative overflow-hidden rounded-3xl border border-[#e5d8cb] bg-[#f1e9df] p-6 sm:p-10">
    <div class="pointer-events-none absolute -right-10 -top-12 size-64 rounded-full border-[35px] border-white/35" aria-hidden="true"></div>
    <div class="relative flex items-start justify-between gap-5">
        <div><p class="eyebrow">{{ $eyebrow }}</p><h1 class="mt-3 font-serif text-3xl leading-tight sm:text-5xl">{{ $title }}</h1>@if($description)<p class="mt-4 max-w-2xl text-sm leading-7 text-muted">{{ $description }}</p>@endif{{ $slot }}</div>
        <span class="hidden size-16 shrink-0 place-items-center rounded-2xl border border-white bg-white/60 text-brand-700 sm:grid"><x-icon :name="$icon" class="size-7"/></span>
    </div>
</div>
