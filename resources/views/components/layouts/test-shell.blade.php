<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-accent="gold" data-density="comfortable" data-theme="light" data-grain="on">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body style="background: var(--ivory-deep); min-height: 100vh;">
    {{ $slot }}
    @livewireScripts
    @fluxScripts
</body>
</html>
