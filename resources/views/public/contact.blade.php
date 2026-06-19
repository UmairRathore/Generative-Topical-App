<x-layouts.public>
    <section class="mx-auto max-w-6xl px-6 pt-20 pb-20 grid md:grid-cols-2 gap-10">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] font-semibold text-brand-gold">Contact</p>
            <h1 class="mt-2 font-display text-4xl md:text-5xl font-semibold tracking-tight text-brand-emerald">Talk to us.</h1>
            <p class="mt-4 text-brand-charcoal/70 max-w-md">
                Schools, tutors, and curriculum designers - we'd love to hear how Generative Topical could help your students.
            </p>
            <ul class="mt-8 space-y-4 text-sm text-brand-charcoal/80">
                <li class="flex items-start gap-3">
                    <flux:icon name="envelope" class="size-5 text-brand-forest" variant="outline" />
                    <span><span class="block text-brand-slate text-xs uppercase tracking-[0.18em]">Email</span>hello@generativetopical.com</span>
                </li>
                <li class="flex items-start gap-3">
                    <flux:icon name="phone" class="size-5 text-brand-forest" variant="outline" />
                    <span><span class="block text-brand-slate text-xs uppercase tracking-[0.18em]">Phone</span>+44 20 0000 0000</span>
                </li>
                <li class="flex items-start gap-3">
                    <flux:icon name="map-pin" class="size-5 text-brand-forest" variant="outline" />
                    <span><span class="block text-brand-slate text-xs uppercase tracking-[0.18em]">Office</span>Cambridge, United Kingdom</span>
                </li>
            </ul>
        </div>

        <x-gt.card padding="p-6 md:p-8">
            <form method="POST" action="#" class="grid gap-4">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block text-sm">
                        <span class="text-brand-charcoal font-medium">First name</span>
                        <input type="text" name="first_name" class="mt-1 w-full rounded-md border border-brand-border focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/20 px-3 py-2 text-sm bg-white" required />
                    </label>
                    <label class="block text-sm">
                        <span class="text-brand-charcoal font-medium">Last name</span>
                        <input type="text" name="last_name" class="mt-1 w-full rounded-md border border-brand-border focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/20 px-3 py-2 text-sm bg-white" required />
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="text-brand-charcoal font-medium">Work email</span>
                    <input type="email" name="email" class="mt-1 w-full rounded-md border border-brand-border focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/20 px-3 py-2 text-sm bg-white" required />
                </label>
                <label class="block text-sm">
                    <span class="text-brand-charcoal font-medium">School / organisation</span>
                    <input type="text" name="org" class="mt-1 w-full rounded-md border border-brand-border focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/20 px-3 py-2 text-sm bg-white" />
                </label>
                <label class="block text-sm">
                    <span class="text-brand-charcoal font-medium">How can we help?</span>
                    <textarea name="message" rows="5" class="mt-1 w-full rounded-md border border-brand-border focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/20 px-3 py-2 text-sm bg-white"></textarea>
                </label>
                <x-gt.button type="submit" variant="primary" size="lg" iconTrailing="paper-airplane">Send message</x-gt.button>
                <p class="text-xs text-brand-slate">We typically reply within one working day.</p>
            </form>
        </x-gt.card>
    </section>
</x-layouts.public>
