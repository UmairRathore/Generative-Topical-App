{{-- Generative Topical crest: shield + open book + pen-nib + AI circuit node --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" {{ $attributes }}>
    {{-- shield --}}
    <path d="M32 4 L56 12 V30 Q56 46 32 60 Q8 46 8 30 V12 Z"
          fill="var(--color-brand-emerald)"
          stroke="var(--color-brand-gold)" stroke-width="1.6" stroke-linejoin="round"/>
    {{-- open book --}}
    <path d="M16 30 Q24 26 32 30 Q40 26 48 30 V42 Q40 38 32 42 Q24 38 16 42 Z"
          fill="var(--color-brand-forest)"
          stroke="var(--color-brand-gold-soft)" stroke-width="1"/>
    <path d="M32 30 V42" stroke="var(--color-brand-gold-soft)" stroke-width="1"/>
    {{-- pen nib --}}
    <path d="M32 14 L36 22 L32 26 L28 22 Z"
          fill="var(--color-brand-gold)"
          stroke="var(--color-brand-gold-soft)" stroke-width="0.6"/>
    <line x1="32" y1="20" x2="32" y2="26"
          stroke="var(--color-brand-emerald)" stroke-width="0.6"/>
    {{-- AI circuit node --}}
    <circle cx="48" cy="16" r="2.2" fill="var(--color-brand-gold-soft)"/>
    <line x1="48" y1="16" x2="42" y2="22"
          stroke="var(--color-brand-gold-soft)" stroke-width="0.8" stroke-dasharray="1.5 1.5"/>
</svg>
