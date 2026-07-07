# 14 — Interactive Widgets

> Code-grounded reference. Verify against source before acting.

**Siblings:** [12 — Mistake Bank](./12-mistake-bank.md) ·
[13 — Learning Hub / Worked Solutions / Assets](./13-learning-hub-worked-solutions-assets.md) ·
[15 — Notes Module](./15-notes-module.md)

---

## Purpose

The interactive-widget layer is a **React island / component library** (`resources/js/widgets/`)
that turns a physics/chemistry/biology question's diagram into a config-driven, interactive
simulation (sliders, draggable elements, live graphs, an "Options probe" that enacts each MCQ answer
on the diagram). One `interactive_widget` learning asset (`{widget, config}`) selects a component
and supplies its props; a finite widget library covers many questions via per-question config.

Status: **Implemented in code.** 31 widget components are registered and render in the studio, the
admin review page, and Notes. Widget *coverage* of the question bank is partial (see
memory `v2-asset-generation`); the infrastructure is complete.

## Users / Roles

- **Students** — play widgets in the mistake studio (`Solution.jsx`) and inside their Notes
  (`WidgetBlock`), reached only through a released mistake (module 12/13).
- **Super-admins** — see widgets in the asset review page (`AssetReview.jsx`).
- **Not persisted per student:** widgets save **no student responses** — they are stateless
  interactives. The only thing ever saved is a widget's *config* when a student adds it to a note
  (module 15).

## Current Implementation

Files under `resources/js/widgets/`:

- `registry.js` — `type → () => import(component)` lazy map; `hasWidget(type)`. **31 active widget
  types** (2 commented-out placeholders: `beam_balance_sim` R3F, `energy_flow_sankey`).
- `index.jsx` — island bootstrap. Any `[data-widget="<type>"][data-config='{…}']` element mounts.
  Exposes `window.CambWidgets`: `.mount(el)`, `.mountAll(root?)`, `.get(el)`,
  `.render(container, type, config)` (build+mount from saved JSON — used by Notes to re-instantiate),
  `.unmount(el)`.
- `WidgetHost.jsx` — per-widget **error boundary + Suspense + the notes contract** (see below).
- `WidgetRenderer.jsx` (**under `resources/js/learn/`, not `widgets/`**) — renders `{type, config}`
  inside an Inertia page reusing the **same registry**; used by `Solution.jsx`, `AssetReview.jsx`,
  and the Notes `WidgetBlock`.
- `primitives.jsx` — shared UI, incl. the **`Options` probe** (`export function Options`, line 58):
  renders the 4 MCQ options as buttons; clicking one calls `onPick(option, i)` to enact it on the
  diagram and marks correct/wrong.
- `lib/formula.js` — a **CSP-safe recursive-descent evaluator** (no `eval` / `new Function`) used by
  the `calculator` widget family. `lib/tokens.js` — design tokens.
- `widgets/*.jsx` — the 31 components (one file each, matched 1:1 to `registry.js`).

### Registered widget types (`registry.js`, verified)
`calculator`, `beam_balance`, `moments_beam`, `graph_explorer`, `ultrasound_echo`,
`circular_motion`, `ray_diagram`, `circuit_network`, `force_vectors`, `pressure_area`,
`liquid_pressure`, `fleming_lhr`, `mirror_view`, `lens_rays`, `ac_generator`, `reflection_angle`,
`critical_angle`, `graph_regions`, `charged_deflection`, `gas_cylinder`, `states_of_matter`,
`heat_transfer`, `potential_divider`, `dc_motor`, `transformer`, `vector_resultant`,
`refraction_block`, `inclined_plane`, `scene` (`SceneDiagram.jsx` — the general 2D scene engine),
`photosynthesis_lake`, and `plank_moments_3d` (the one genuine **3D** widget — three.js, lazy
~527 KB chunk, per `WidgetBlock`/registry comment).

**Authoritative render list** = `registry.js`. **`LearningAssetValidator::KNOWN_WIDGETS`**
(`app/Support/LearningAssetValidator.php:15`) must mirror it; a widget type absent there is flagged
"has no component yet — will not render" at review time.

## Data Model

Widgets have **no table of their own.** Their config lives in the `interactive_widget` row of
`v2_question_learning_assets` (module 13):

```
payload_json = { "widget": "<type>", "config": { …widget props… } }
```
`Solution.jsx` receives it via `LearningHubController::studio()` as
`widget = { type: payload.widget ?? asset_key, config: payload.config ?? payload }`
(`LearningHubController.php:146-149`). When a student saves a widget to a note, only
`{widgetType, config}` (config = the widget's live state) is stored on the note block — no image, no
server state (module 15, `NotesBlockMapper::widgetStateBlock`).

## Core Flows

### Rendering a question widget
1. Approved `interactive_widget` asset exists → `studio()` passes `{type, config}`.
2. `Solution.jsx` → `WidgetRenderer` → `registry[type]()` lazy import → component mounts with
   `config` + `onAddToNote`.

### The notes contract (`WidgetHost.jsx`, verified)
- The widget calls `onReady(api)` → the host stashes an imperative handle on the DOM node
  (`el.widgetApi`) and registers it in `window.CambWidgets`.
- "Save to note" → the widget calls `onAddToNote(config)` → the host dispatches a framework-agnostic
  DOM event **`camb:add-to-note`** with `detail = { widget: type, config }` (bubbles).
- The host app decides what to do with that intent. In production the listener is the Notes studio;
  the import stores `{widget, config}` and re-mounts live via `window.CambWidgets.render()` /
  `WidgetRenderer` (module 15).

### Re-mounting a saved widget in a note
`WidgetBlock.jsx` parses the stored `config` JSON string and renders `<WidgetRenderer type config>`
— the exact same registry/components as the island, so a saved diagram is the widget as the student
left it, still fully playable. Block is `contentEditable={false}` so ProseMirror never touches the
widget's DOM.

## Inputs

- **Config** from the `interactive_widget` asset payload (per-question).
- **Student interaction** (sliders/drags/option clicks) — transient, in-component state only.

## Outputs

- Rendered interactive DOM (canvas/SVG sims).
- On "add to note": a `camb:add-to-note` event → a `{widget, config}` note block. Nothing else
  leaves the widget.

## Dependencies

- `v2_question_learning_assets.interactive_widget` (module 13) — config source.
- `LearningAssetValidator::KNOWN_WIDGETS` — the review-time "will this render?" check.
- React 18.3 + Vite (`@vitejs/plugin-react`); build writes `public/build/manifest.json`.
  **Build quirk** (memory `v2-react-widget-islands`): node is under nvm; prefix npm with the nvm
  PATH export. `plank_moments_3d` pulls three.js 0.128 as a lazy chunk.
- **License note (in code/decisions):** the two-column notes block was built custom instead of
  `@blocknote/xl-multi-column` (GPL/commercial) to keep the tree MPL-clean — see module 15. No
  license-encumbered widget dependency is present in `registry.js`.

## Security / Access Rules

- Widgets render only for approved assets reached through a released mistake (inherits module 12/13
  gates); there is no direct widget route for students.
- The formula evaluator is **CSP-safe** (`lib/formula.js`, no `eval`/`new Function`).
- Widget re-mount from a note is constrained: on import the widget **type must match the question's
  own visible widget** (`NotesImportController::widgetStateBlocks`) so an arbitrary widget/config
  pair can't be injected (module 15).
- Config is JSON only; BlockNote widget-block props are primitive (config is a JSON **string** prop).

## Existing AI-Relevant Context

- A widget config is a compact, structured description of a diagram's interactive model
  (`{widget, config}`) — an AI could read it to understand what a question is testing, or author one.
- The `Options` probe config carries per-option `{label, note, correct, …enact-fields}` — a
  machine-readable distractor model (what each wrong answer looks like on the diagram).
- `SceneDiagram.jsx` (`scene`) is the general-purpose 2D engine most bespoke diagrams collapse into,
  per the generation pipeline (memory `v2-asset-generation`).

## AI Opportunities

- **Widget-config authoring** — given a question + its diagram, an AI could propose an
  `interactive_widget` config for an existing widget type (validated by `KNOWN_WIDGETS` +
  `LearningAssetValidator`), landing as a `draft` asset for review. *Recommended for AI.*
- **Explain-the-sim** — a tutor could describe what a widget shows using its config, grounded, no
  free physics. *Recommended for AI.*

## AI Risks

- **Config must reference a registered type** or it silently won't render (the validator warns; a
  student would see the `cw-error` fallback). AI must pick from `registry.js` types only.
- **Widgets store no student answers** — do not assume a widget interaction is a persisted signal;
  the behavioural signal lives in the Mistake Bank (module 12), not here.
- **Fidelity rule (from code review history):** a widget must depict the question's actual figure
  and be interactive; a mismatched picture is worse than a static image (memory
  `v2-asset-generation`). AI-authored configs should respect this.

## Future Improvements

- Grow widget coverage of the bank (the long tail of bespoke diagram types) — infrastructure is
  done; each new type is one registry line + one component + a `KNOWN_WIDGETS` entry.
- A topic/subject-level 3D concept layer (a handful of inherently-3D concepts) is planned separate
  from per-question diagrams (memory `v2-asset-generation` Phase 3). *Future.*
- Wire the commented-out placeholders (`beam_balance_sim`, `energy_flow_sankey`) if needed. *Not implemented.*
