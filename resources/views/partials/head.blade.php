<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name', 'Generative Topical') }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fraunces:400,500,600,700|instrument-sans:400,500,600|cormorant-garamond:400,500,600,700:italic" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Default appearance to "light" on first visit (rather than following the OS).
     Must run BEFORE @fluxAppearance so it reads our default. --}}
<script>
    (function () {
        try {
            if (!localStorage.getItem('flux.appearance')) {
                localStorage.setItem('flux.appearance', 'light');
            }
        } catch (e) {}
    })();
</script>

@fluxAppearance
