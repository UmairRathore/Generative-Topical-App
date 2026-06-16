{{-- Separates the answer choices from the question. Shows an "Options" label,
     then the option-table image (option_table layout — the table IS the answers),
     so answer diagrams are never mistaken for question diagrams.
     Prop: $q (a V2 Question with `images` loaded). --}}
<div class="flex items-center gap-2" style="margin: 20px 0 12px;">
    <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.09em; color:var(--text-faint);">Options</span>
    <span style="flex:1; height:1px; background:var(--border);"></span>
</div>

@php $optTable = $q->optionTableImage(); @endphp
@if ($optTable)
    {{-- answer table: borderless, centered, uniform-scaled (a touch bigger than
         question diagrams as it holds denser content); max-width:100% keeps it in-column. --}}
    @php $tw = $optTable->displayWidth(); @endphp
    <div class="v2-table-wrap">
        <img src="{{ asset('storage/'.$optTable->image_path) }}" alt="options table" loading="lazy" onerror="this.style.display='none'"
             @if ($tw) style="width:{{ $tw }}px;" @endif>
    </div>
@endif
