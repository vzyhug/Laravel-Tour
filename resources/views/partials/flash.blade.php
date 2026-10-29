@php
    $alertVariant = session('warning') ? 'warning' : ((session('error') || $errors->any()) ? 'error' : 'success');
    $alertMessages = collect([session('warning'), session('error'), session('success')])
        ->filter(fn ($message) => filled($message))
        ->merge($errors->all())
        ->values();
@endphp

@if($alertMessages->isNotEmpty())
    @include('components.flash-alert', ['alertId' => 'flash-alert', 'alertTitle' => session('alert_title')])
@endif
<template data-flash-alert-template>
    @include('components.flash-alert', ['alertId' => 'dynamic-flash-alert', 'alertVariant' => 'success', 'alertTitle' => null, 'alertMessages' => []])
</template>
