<x-layouts.public>
    {{-- Hero --}}
    <section class="grid items-center" style="padding: 100px 60px 70px; max-width: 1320px; margin: 0 auto; grid-template-columns: 1.1fr 1fr; gap: 60px;">
        <div>
            <div class="uppercase-eyebrow">Cambridge MCQ Generator · Built in Pakistan</div>
            <h1 class="serif" style="font-size: 76px; font-weight: 600; line-height: 1.04; letter-spacing: -0.025em; margin-top: 18px;">
                The MCQ engine your <em style="color: var(--gold-700); font-style: italic;">O & A Level</em> students were waiting for.
            </h1>
            <p style="font-size: 18px; color: var(--text-soft); line-height: 1.55; margin-top: 24px; max-width: 540px;">
                Generate 40-question Cambridge-style papers in seconds, or hand-pick from 50,000+ moderated questions. Built with Lahore Grammar, Aitchison and KGS faculty.
            </p>
            <div class="flex" style="gap: 14px; margin-top: 36px;">
                <a href="{{ route('register') }}" wire:navigate class="btn btn-primary" style="padding: 14px 24px; font-size: 14px;">
                    Start free trial<x-icon name="chev-r" size="14" stroke="2.4"/>
                </a>
                <button class="btn btn-ghost" style="padding: 14px 24px; font-size: 14px;"><x-icon name="play" size="13"/>Watch 90s demo</button>
            </div>
            <div class="flex items-center" style="margin-top: 40px; gap: 24px; padding-top: 28px; border-top: 1px solid var(--border-soft);">
                <div style="font-size: 11px; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.1em;">Trusted by</div>
                <div class="flex items-center serif" style="gap: 28px; font-weight: 600; font-size: 14px; color: var(--text-soft);">
                    <span>LGS</span><span>Aitchison</span><span>KGS</span><span>BSS</span><span>Roots</span>
                </div>
            </div>
        </div>

        {{-- Hero artwork: paper preview cards --}}
        <div class="relative pub-decor" style="height: 580px;">
            <div style="position: absolute; top: 30px; left: 60px; width: 360px; transform: rotate(-3deg); background: var(--surface); border-radius: 12px; box-shadow: var(--shadow-md); padding: 28px; border: 1px solid var(--border);">
                <div class="flex items-center justify-between" style="margin-bottom: 12px;">
                    <x-crest size="24"/>
                    <span class="mono" style="font-size: 10px; color: var(--gold-700);">9702/12 · 2025</span>
                </div>
                <div class="serif" style="font-size: 16px; font-weight: 600;">A Level Physics — Paper 1</div>
                <div style="font-size: 11px; color: var(--text-faint);">40 multiple choice · 1 hour</div>
                <hr class="gold-rule" style="margin: 14px 0;"/>
                <div class="serif" style="font-size: 12px; line-height: 1.5; color: var(--text-soft);">
                    <b>1.</b> A car of mass 1200 kg accelerates uniformly from rest to 25 m s⁻¹ in 8.0 s. The resultant force on the car is…
                </div>
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 10px; font-size: 11px;">
                    @foreach(['A 150 N','B 1500 N','C 3750 N','D 9600 N'] as $o)
                        <div style="padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px;">{{ $o }}</div>
                    @endforeach
                </div>
            </div>
            <div style="position: absolute; top: 100px; right: 0; width: 320px; transform: rotate(4deg); background: var(--emerald-900); color: var(--ivory); border-radius: 12px; box-shadow: var(--shadow-md); padding: 24px; overflow: hidden;">
                <div class="grain absolute" style="inset: 0; border-radius: 12px;"></div>
                <div class="relative">
                    <div class="uppercase-eyebrow" style="color: var(--accent);">Result</div>
                    <div class="serif" style="font-size: 56px; font-weight: 600; color: var(--accent); margin-top: 6px; line-height: 1;">84%</div>
                    <div style="font-size: 12px; color: rgba(250,247,239,0.7);">34 / 40 correct</div>
                    <hr style="margin: 14px 0; border: 0; border-top: 1px solid rgba(212,164,55,0.25);"/>
                    <div class="flex flex-col" style="gap: 8px;">
                        @foreach([['Mechanics',92], ['Waves',78], ['Atomic',55]] as [$t, $p])
                            <div>
                                <div class="flex justify-between" style="font-size: 11px;"><span>{{ $t }}</span><span style="color: var(--accent); font-weight: 600;">{{ $p }}%</span></div>
                                <div style="height: 3px; background: rgba(255,255,255,0.1); border-radius: 2px; margin-top: 3px;">
                                    <div style="width: {{ $p }}%; height: 100%; background: var(--accent);"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex items-center"
                 style="position: absolute; bottom: 20px; left: 30px; padding: 16px 22px; background: var(--surface); border-radius: 10px; box-shadow: var(--shadow-md); border: 1px solid var(--border); gap: 12px; width: 280px;">
                <div class="flex items-center justify-center" style="width: 40px; height: 40px; border-radius: 8px; background: var(--gold-50); color: var(--gold-700);">
                    <x-icon name="sparkle" size="18"/>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600;">40 questions in 7 seconds</div>
                    <div style="font-size: 11px; color: var(--text-faint);">From 50,000 moderated questions</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section style="padding: 60px 60px; max-width: 1320px; margin: 0 auto;">
        <div class="uppercase-eyebrow">How it works</div>
        <h2 class="serif" style="font-size: 44px; font-weight: 600; letter-spacing: -0.015em; max-width: 700px; margin-top: 10px;">
            A test bank, a generator and an analytics suite — under one roof.
        </h2>
        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 50px;">
            @foreach([
                ['sparkle',  '40-question Generator', "Pick a subject, allocate questions across topics, choose Cambridge-style settings. Done."],
                ['filter',   'Selective Picker',      "Filter by topic, year, paper, difficulty, and curate your own paper question by question."],
                ['trending', 'Mastery Analytics',     "See exactly which topics — and which students — need attention. Generate focused remediation papers."],
            ] as [$icon, $t, $d])
                <div class="card-elev" style="padding: 28px;">
                    <div class="flex items-center justify-center" style="width: 44px; height: 44px; border-radius: 8px; background: var(--gold-50); color: var(--gold-700); margin-bottom: 18px;">
                        <x-icon :name="$icon" size="20"/>
                    </div>
                    <h3 class="serif" style="font-size: 22px; font-weight: 600;">{{ $t }}</h3>
                    <p style="color: var(--text-soft); font-size: 14px; line-height: 1.55; margin-top: 8px;">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section style="padding: 80px 60px; max-width: 1320px; margin: 0 auto;">
        <div class="grid items-center relative pub-cta"
             style="background: linear-gradient(135deg, var(--emerald-900), var(--emerald-800)); color: var(--ivory); border-radius: 18px; padding: 60px 70px; overflow: hidden; grid-template-columns: 2fr 1fr; gap: 40px;">
            <div class="grain absolute" style="inset: 0;"></div>
            <div class="relative">
                <div class="uppercase-eyebrow" style="color: var(--accent);">For schools</div>
                <h2 class="serif" style="font-size: 40px; font-weight: 600; line-height: 1.1; margin-top: 12px; letter-spacing: -0.015em;">
                    Bring your whole department on board.
                </h2>
                <p style="font-size: 16px; color: rgba(250,247,239,0.8); line-height: 1.55; margin-top: 16px; max-width: 520px;">
                    School plans include unlimited tests, full analytics, custom branding, and a private question bank for your faculty.
                </p>
            </div>
            <div class="relative flex flex-col" style="gap: 10px;">
                <a href="{{ route('pricing') }}" wire:navigate class="btn btn-gold" style="padding: 14px 22px; font-size: 14px;">View school pricing</a>
                <a href="{{ route('contact') }}" wire:navigate class="btn btn-outline-light" style="padding: 14px 22px; font-size: 14px;">Talk to our team</a>
            </div>
        </div>
    </section>
</x-layouts.public>
