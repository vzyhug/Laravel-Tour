<nav class="container-page flex gap-2 overflow-x-auto py-4" aria-label="Dịch vụ VietGo">
    <a href="{{ route('tours.index') }}" class="btn-secondary shrink-0">Tour du lịch</a>
    @foreach(\App\Enums\ServiceType::cases() as $item)<a href="{{ route('services.index', $item->value) }}" class="{{ ($service ?? null) === $item ? 'btn-primary' : 'btn-secondary' }} shrink-0" @if(($service ?? null) === $item) aria-current="page" @endif>{{ $item->label() }}</a>@endforeach
</nav>
