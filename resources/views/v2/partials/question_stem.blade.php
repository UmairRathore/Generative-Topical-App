{{-- Renders a question stem in true reading order. The ordering (text/diagram
     interleaving, reconstructed from text segments + bbox positions) lives in
     Question::stemBlocks(); this partial only applies the per-role container.
     Aspect ALWAYS preserved — never stretched, distorted, or recropped.
     Prop: $q (a V2 Question with `images` loaded). --}}
@php
    $figStyle = 'display:block; max-width:100%; max-height:480px; width:auto; height:auto; object-fit:contain; margin:14px auto; border:1px solid var(--border); border-radius:8px; background:#fff;';
    $txtStyle = 'font-size:15px; line-height:1.55; color:var(--text); margin-top:10px;';
@endphp

@foreach ($q->stemBlocks() as $block)
    @if ($block['type'] === 'text')
        <div style="{{ $txtStyle }}">{{ $block['text'] }}</div>
    @elseif ($block['type'] === 'figure')
        {{-- main diagram: big, centered, capped to column + 480px tall --}}
        <img src="{{ asset('storage/'.$block['image']->image_path) }}" alt="diagram" loading="lazy" onerror="this.style.display='none'" style="{{ $figStyle }}">
    @endif
@endforeach
