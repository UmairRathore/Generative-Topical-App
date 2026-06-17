{{-- Separates the answer choices from the question. Shows an "Options" label,
     then the option-table image (option_table layout — the table IS the answers),
     so answer diagrams are never mistaken for question diagrams.
     Prop: $q (a V2 Question with `images` loaded). --}}
<div class="flex items-center gap-2" style="margin: 20px 0 12px;">
    <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.09em; color:var(--text-faint);">Options</span>
    <span style="flex:1; height:1px; background:var(--border);"></span>
</div>

{{-- For option_table questions the answer table is rendered alongside the
     selectable A/B/C/D letters by the take / review views (see .v2-table-pick),
     so it is intentionally not drawn here. --}}
