@php
    $tiers = [
        [
            'name' => 'Student',
            'priceM' => 1500, 'priceY' => 14400,
            'desc' => 'For self-study and exam prep',
            'features' => ['Unlimited practice tests', 'All subjects O & A Level', 'Detailed analytics', 'Mistake review with explanations', 'Mobile + tablet apps'],
            'cta' => 'Start free trial',
            'featured' => false,
        ],
        [
            'name' => 'Teacher',
            'priceM' => 3500, 'priceY' => 33600,
            'desc' => 'For tutors and individual teachers',
            'features' => ['Everything in Student', '40-question paper generator', 'Selective question picker', 'Up to 60 students', 'Class analytics & exports', 'Custom branded papers'],
            'cta' => 'Start free trial',
            'featured' => true,
        ],
        [
            'name' => 'School',
            'priceM' => null, 'priceY' => null,
            'desc' => 'For institutions, departments and chains',
            'features' => ['Everything in Teacher', 'Unlimited teachers & students', 'Department-level analytics', 'SSO + roster sync', 'Private question bank', 'Dedicated support manager', 'Onboarding included'],
            'cta' => 'Contact sales',
            'featured' => false,
        ],
    ];
@endphp
<x-layouts.public title="Pricing">
    <section class="text-center" style="max-width: 1240px; margin: 0 auto; padding: 80px 60px 40px;">
        <div class="uppercase-eyebrow" style="justify-content: center;">Pricing</div>
        <h1 class="serif" style="font-size: 60px; font-weight: 600; letter-spacing: -0.02em; line-height: 1.05; margin-top: 14px;">
            Plans that grow <em style="color: var(--gold-700); font-style: italic;">with you.</em>
        </h1>
        <p style="font-size: 17px; color: var(--text-soft); line-height: 1.55; margin-top: 18px; max-width: 580px; margin-left: auto; margin-right: auto;">
            14-day free trial on every plan. Pakistani Rupee billing. Switch or cancel any time.
        </p>
    </section>

    <section style="max-width: 1240px; margin: 0 auto; padding: 20px 60px 60px;">
        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 18px;">
            @foreach($tiers as $t)
                @php $f = $t['featured']; @endphp
                <div class="relative"
                     style="background: {{ $f ? 'var(--emerald-900)' : 'var(--surface)' }}; color: {{ $f ? 'var(--ivory)' : 'var(--text)' }}; border: {{ $f ? '0' : '1px solid var(--border)' }}; border-radius: 14px; padding: 36px; box-shadow: {{ $f ? '0 24px 60px -20px rgba(11,61,46,0.45)' : 'var(--shadow-sm)' }}; transform: {{ $f ? 'translateY(-12px)' : 'none' }};">
                    @if($f)
                        <div class="absolute" style="top: -14px; left: 50%; transform: translateX(-50%); background: var(--accent); color: var(--emerald-900); padding: 5px 14px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;">Most popular</div>
                    @endif
                    <div style="font-size: 12px; font-weight: 600; color: {{ $f ? 'var(--accent)' : 'var(--gold-700)' }}; text-transform: uppercase; letter-spacing: 0.1em;">{{ $t['name'] }}</div>
                    <p style="font-size: 13px; color: {{ $f ? 'rgba(250,247,239,0.7)' : 'var(--text-soft)' }}; margin-top: 6px; min-height: 36px;">{{ $t['desc'] }}</p>
                    <div style="margin-top: 22px; margin-bottom: 22px;">
                        @if($t['priceM'])
                            <div class="flex items-baseline" style="gap: 6px;">
                                <span style="font-size: 14px; font-weight: 600; opacity: 0.6;">PKR</span>
                                <span class="serif" style="font-size: 56px; font-weight: 600; line-height: 1; letter-spacing: -0.02em;">{{ number_format($t['priceM']) }}</span>
                                <span style="font-size: 14px; opacity: 0.6;">/mo</span>
                            </div>
                            <div style="font-size: 12px; color: {{ $f ? 'rgba(250,247,239,0.6)' : 'var(--text-faint)' }}; margin-top: 4px;">or PKR {{ number_format($t['priceY']) }}/year (save 20%)</div>
                        @else
                            <div class="serif" style="font-size: 36px; font-weight: 600; line-height: 1.1; letter-spacing: -0.01em;">Custom</div>
                        @endif
                    </div>
                    <a href="{{ $t['name'] === 'School' ? route('contact') : route('register') }}" wire:navigate class="btn {{ $f ? 'btn-gold' : 'btn-primary' }}" style="width: 100%; padding: 12px 20px; font-size: 14px;">{{ $t['cta'] }}</a>
                    <hr style="margin: 24px 0; border: 0; border-top: 1px solid {{ $f ? 'rgba(212,164,55,0.2)' : 'var(--border-soft)' }};"/>
                    <div class="flex flex-col" style="gap: 10px;">
                        @foreach($t['features'] as $feat)
                            <div class="flex items-start" style="gap: 10px; font-size: 13px;">
                                <div class="flex items-center justify-center" style="flex: none; width: 18px; height: 18px; border-radius: 50%; background: {{ $f ? 'rgba(212,164,55,0.18)' : 'var(--gold-50)' }}; color: {{ $f ? 'var(--accent)' : 'var(--gold-700)' }}; margin-top: 1px;">
                                    <x-icon name="check" size="11" stroke="2.6"/>
                                </div>
                                <span style="line-height: 1.5;">{{ $feat }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
