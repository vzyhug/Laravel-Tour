@if(session('success') || session('error') || $errors->any())
<div class="container-page pt-4" role="alert">
    <div class="rounded-xl border px-4 py-3 text-sm {{ session('error') || $errors->any() ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
        @if(session('success'))<p>{{ session('success') }}</p>@endif
        @if(session('error'))<p>{{ session('error') }}</p>@endif
        @if($errors->any())<ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    </div>
</div>
@endif
