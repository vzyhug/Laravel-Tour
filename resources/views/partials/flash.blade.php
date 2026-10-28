@php
    $alertMessages = collect([session('success'), session('error')])
        ->filter(fn ($message) => filled($message))
        ->merge($errors->all())
        ->values();
@endphp

@if($alertMessages->isNotEmpty())
    <script>
        window.alert(@json($alertMessages->implode("\n\n")));
    </script>
@endif
