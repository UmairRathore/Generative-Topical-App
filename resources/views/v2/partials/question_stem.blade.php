{{-- Renders a question stem in true reading order. The ordering (text/diagram
     interleaving, reconstructed from text segments + bbox positions) lives in
     Question::stemBlocks(); this partial only applies the per-role container.
     Aspect ALWAYS preserved - never stretched, distorted, or recropped.
     Prop: $q (a V2 Question with `images` loaded). --}}
@php
    // No top margin on the first block, so it lines up with the question-number
    // badge (which sits at the top via items-start). Spacing only goes between blocks.
    $txtStyle = 'font-size:15px; line-height:1.55; color:var(--text);';
@endphp

@foreach ($q->stemBlocks() as $block)
    @if ($block['type'] === 'text')
        <div style="{{ $txtStyle }}{{ $loop->first ? '' : ' margin-top:10px;' }}">{!! sci($block['text']) !!}</div>
    @elseif ($block['type'] === 'figure')
        {{-- centered, borderless figure. Width is uniform-scaled from the crop's
             own point size (see QuestionImage::displayWidth) so label text stays
             one consistent size across diagrams; max-width:100% keeps it in-column. --}}
        @php $fw = $block['image']->displayWidth(); @endphp
        <div class="v2-figure-wrap" @if ($loop->first) style="margin-top:0;" @endif>
            <img src="{{ simg($block['image']->image_path) }}" alt="diagram" loading="lazy" onerror="this.style.display='none'"
                 @if ($fw) style="width:{{ $fw }}px;" @endif>
        </div>
    @endif
@endforeach
