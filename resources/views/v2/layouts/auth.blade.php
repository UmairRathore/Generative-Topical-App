<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-accent="gold" data-density="comfortable" data-theme="light" data-grain="on">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body class="paper-grain" style="background: var(--ivory-deep); min-height: 100vh;">
    @yield('content')
    @livewireScripts
    @fluxScripts
</body>
</html>
