<!DOCTYPE html>
<html lang="en" data-mode="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>Learning Studio</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet">

    {{-- App design tokens (brand: --ok, --accent, --bad, --paper …) --}}
    @include('v2.partials.theme')

    {{-- Required for React under the Vite DEV server (no-op in built mode). --}}
    @viteReactRefresh
    @vite(['resources/js/learn/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
