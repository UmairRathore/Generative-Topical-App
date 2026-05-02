<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-brand-ivory text-brand-charcoal antialiased">
    <div class="flex min-h-svh items-center justify-center p-6">
        <div class="w-full max-w-md">

            {{-- Brand crest --}}
            <a href="{{ route('home') }}" class="block text-center" wire:navigate>
                <div class="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-md bg-brand-emerald shadow-sm">
                    <span class="font-display text-xl font-semibold text-brand-gold">GT</span>
                </div>
                <div class="font-display text-2xl font-semibold tracking-tight text-brand-emerald">Generative Topical</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">AI-Powered Assessment Platform</div>
                <div class="mt-1 text-[11px] tracking-wider text-brand-slate/80">Cambridge · O Level · A Level · IGCSE</div>
            </a>

            {{-- Card with gold accent --}}
            <div class="mt-6 overflow-hidden rounded-xl border border-brand-border bg-white shadow-sm">
                <div class="h-1 bg-gradient-to-r from-brand-gold via-brand-gold-soft to-brand-gold"></div>
                <div class="px-8 py-8 text-brand-charcoal">
                    {{ $slot }}
                </div>
            </div>

            <p class="mt-6 text-center text-[11px] text-brand-slate">
                © {{ date('Y') }} Generative Topical · Trusted by educators across Pakistan & beyond
            </p>
        </div>
    </div>
    @fluxScripts
</body>
</html>
