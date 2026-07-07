// ── Widget registry ──────────────────────────────────────────────────────────
// Maps a stable widget-type id (the same `widget` value produced by the tagging
// pipeline) to a lazily-imported React component. Every interactive question
// references one of these by type; the per-question `config` supplies its props.
//
// Adding a widget = one line here + one component file. This is the finite
// ~100-template library that covers all 19,760 questions via per-question config.

export const registry = {
    calculator:          () => import('./widgets/Calculator.jsx'),
    beam_balance:        () => import('./widgets/BeamBalance.jsx'),
    moments_beam:        () => import('./widgets/MomentsBeam.jsx'),
    graph_explorer:      () => import('./widgets/GraphExplorer.jsx'),
    ultrasound_echo:     () => import('./widgets/UltrasoundEcho.jsx'),
    circular_motion:     () => import('./widgets/CircularMotion.jsx'),
    ray_diagram:         () => import('./widgets/RayDiagram.jsx'),
    circuit_network:     () => import('./widgets/CircuitNetwork.jsx'),
    force_vectors:       () => import('./widgets/ForceVectors.jsx'),
    pressure_area:       () => import('./widgets/PressureArea.jsx'),
    liquid_pressure:     () => import('./widgets/LiquidPressure.jsx'),
    fleming_lhr:         () => import('./widgets/FlemingLHR.jsx'),
    mirror_view:         () => import('./widgets/MirrorView.jsx'),
    lens_rays:           () => import('./widgets/LensRays.jsx'),
    ac_generator:        () => import('./widgets/AcGenerator.jsx'),
    reflection_angle:    () => import('./widgets/ReflectionAngle.jsx'),
    critical_angle:      () => import('./widgets/CriticalAngle.jsx'),
    graph_regions:       () => import('./widgets/GraphRegions.jsx'),
    charged_deflection:  () => import('./widgets/ChargedDeflection.jsx'),
    gas_cylinder:        () => import('./widgets/GasCylinder.jsx'),
    states_of_matter:    () => import('./widgets/StatesOfMatter.jsx'),
    heat_transfer:       () => import('./widgets/HeatTransfer.jsx'),
    potential_divider:   () => import('./widgets/PotentialDivider.jsx'),
    dc_motor:            () => import('./widgets/DcMotor.jsx'),
    transformer:         () => import('./widgets/Transformer.jsx'),
    vector_resultant:    () => import('./widgets/VectorResultant.jsx'),
    refraction_block:    () => import('./widgets/RefractionBlock.jsx'),
    inclined_plane:      () => import('./widgets/InclinedPlane.jsx'),
    scene:               () => import('./widgets/SceneDiagram.jsx'),
    photosynthesis_lake: () => import('./widgets/PhotosynthesisLake.jsx'),
    plank_moments_3d:    () => import('./widgets/PlankMoments3D.jsx'),   // genuine 3D (three.js, lazy)
    // beam_balance_sim:   () => import('./widgets/BeamBalanceSim.jsx'),   // (R3F, next)
    // energy_flow_sankey: () => import('./widgets/EnergyFlowSankey.jsx'),
};

export function hasWidget(type) {
    return Object.prototype.hasOwnProperty.call(registry, type);
}
