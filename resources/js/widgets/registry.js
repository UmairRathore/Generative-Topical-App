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
    motion_lab:          () => import('./widgets/MotionLab.jsx'),      // bespoke 1.2 hero: journey + twin live graphs
    skydive_lab:         () => import('./widgets/SkydiveLab.jsx'),     // bespoke 1.5.2 hero: terminal-velocity fall
    weigh_lab:           () => import('./widgets/WeighLab.jsx'),       // bespoke 1.3 hero: three instruments, any world
    density_lab:         () => import('./widgets/DensityLab.jsx'),     // bespoke 1.4 hero: displacement determination
    spring_lab:          () => import('./widgets/SpringLab.jsx'),      // bespoke 1.5.3 hero: live load-extension rig
    orbit_lab:           () => import('./widgets/OrbitLab.jsx'),       // bespoke 1.5.4 hero: qualitative orbit + cut string
    freebody_lab:        () => import('./widgets/FreeBodyLab.jsx'),    // bespoke 1.5.1a hero: free-body diagram builder
    cart_lab:            () => import('./widgets/CartLab.jsx'),        // bespoke 1.5.1b hero: F=ma trolley + live v-t graph
    pendulum_lab:        () => import('./widgets/PendulumLab.jsx'),    // bespoke 1.1m hero: measure-many timing with reaction wobble
    vector_lab:          () => import('./widgets/VectorLab.jsx'),      // bespoke 1.1v hero: river-crossing right-angle resultant
    stability_lab:       () => import('./widgets/StabilityLab.jsx'),   // bespoke 1.5.6 hero: plumb-line CG + tilt stability
    collision_lab:       () => import('./widgets/CollisionLab.jsx'),   // bespoke 1.6 hero: cart collisions + live momentum bars
    energy_park:         () => import('./widgets/EnergyPark.jsx'),     // bespoke 1.7.1 hero: valley skater + live store bars
    work_lab:            () => import('./widgets/WorkLab.jsx'),        // bespoke 1.7.2 hero: W=Fd as a growing area + zero-work carry
    grid_lab:            () => import('./widgets/GridLab.jsx'),        // bespoke 1.7.3 hero: 9 sources, chains + availability + trades
    efficiency_lab:      () => import('./widgets/EfficiencyLab.jsx'),  // bespoke 1.7.4 hero: winch split into useful + wasted, both % forms
    power_lab:           () => import('./widgets/PowerLab.jsx'),       // bespoke 1.7.5 hero: same-work lift race, P=W/t live
    pressure_lab:        () => import('./widgets/PressureLab.jsx'),    // bespoke 1.8 hero: press dent p=F/A + depth probe ρgΔh
    matter_lab:          () => import('./widgets/MatterLab.jsx'),      // bespoke 2.1.1 hero: state morph + legal arrows + tilt/squeeze
    particle_lab:        () => import('./widgets/ParticleLab.jsx'),    // bespoke 2.1.2 hero: particle chamber + gauge + p1V1=p2V2
    expansion_lab:       () => import('./widgets/ExpansionLab.jsx'),   // bespoke 2.2.1 hero: expansion race + dual °C/K scales
    shc_lab:             () => import('./widgets/ShcLab.jsx'),         // bespoke 2.2.2 hero: joulemeter race + live c=ΔE/(mΔθ)
    conduction_lab:      () => import('./widgets/ConductionLab.jsx'),  // bespoke 2.3.1 hero: metal/non-metal race + lattice+electron mechanism
    convection_lab:      () => import('./widgets/ConvectionLab.jsx'),  // bespoke 2.3.2 hero: density-loop beaker/room + dye/smoke tracer
    radiation_lab:       () => import('./widgets/RadiationLab.jsx'),   // bespoke 2.3.3 hero: Leslie cube emit + plate absorb + vacuum/space
    applications_lab:    () => import('./widgets/ApplicationsLab.jsx'),// bespoke 2.3.4 hero: pan/room/IR-thermometer/vacuum-flask synthesis
    wave_lab:            () => import('./widgets/WaveLab.jsx'),        // bespoke 3.1 hero: transverse/longitudinal + shimmering ripple tank
    reflection_lab:      () => import('./widgets/ReflectionLab.jsx'),  // bespoke 3.2.1 hero: law of reflection (i=r) + plane-mirror virtual image
    refraction_lab:      () => import('./widgets/RefractionLab.jsx'),  // bespoke 3.2.2 hero: glass block + critical angle/TIR + optical fibre
    lens_lab:            () => import('./widgets/LensLab.jsx'),        // bespoke 3.2.3 hero: focal point + ray-diagram real/virtual + the eye
    prism_lab:           () => import('./widgets/PrismLab.jsx'),       // bespoke 3.2.4 hero: dispersion prism showpiece + visible-spectrum ordering
    spectrum_lab:        () => import('./widgets/SpectrumLab.jsx'),    // bespoke 3.3 hero: travellable EM spectrum radio→gamma + uses + hazards + constant speed
    sound_lab:           () => import('./widgets/SoundLab.jsx'),       // bespoke 3.4 hero: longitudinal wave + oscilloscope + bell-jar vacuum + sonar echo
    magnet_lab:          () => import('./widgets/MagnetLab.jsx'),      // bespoke 4.1 hero: poles/induced + materials temp-vs-perm + traced dipole field (lines/filings/compass)
    magnet_field_3d:     () => import('./widgets/MagnetField3D.jsx'),  // bespoke 4.1 hero (3D/three.js): orbitable dipole field cage + paper-slice toggle (fields wrap in 3D)
    magnet_poles_3d:     () => import('./widgets/MagnetPoles3D.jsx'),  // bespoke 4.1 hero (3D/three.js): two bar magnets attract/repel + 4-pole field linking vs clashing
    charge_lab:          () => import('./widgets/ChargeLab.jsx'),      // bespoke 4.2.1 hero (2D): charging by friction + attract/repel + conductor/insulator electron model + E-field patterns
    current_lab:         () => import('./widgets/CurrentLab.jsx'),     // bespoke 4.2.2 hero (2D): electron drift + conventional/electron arrows + I=Q/t + a.c./d.c. graph + ammeter
    volt_lab:            () => import('./widgets/VoltLab.jsx'),        // bespoke 4.2.3 hero (2D): energy-per-coulomb e.m.f./p.d. + voltmeter in parallel + cells series/parallel
    resistance_lab:      () => import('./widgets/ResistanceLab.jsx'),  // bespoke 4.2.4 hero (2D): R=V/I measure + I-V graphs (resistor/lamp/diode) + wire R∝L,R∝1/A
    circuit_lab:         () => import('./widgets/CircuitLab.jsx'),     // bespoke 4.3.1/4.3.2 hero (2D): standard-symbol gallery + series (R=R1+R2) + parallel (combined R) with live current/p.d. rules
    sensor_lab:          () => import('./widgets/SensorLab.jsx'),      // bespoke 4.3.3 hero (2D): NTC thermistor/LDR R-vs-stimulus + variable potential divider (R1/R2=V1/V2) + sensor-in-divider input circuit
    power_elec_lab:      () => import('./widgets/PowerElecLab.jsx'),   // bespoke 4.4.1 hero (2D): P=IV per appliance + E=IVt accumulating over time + kilowatt-hour cost meter
    safety_lab:          () => import('./widgets/SafetyLab.jsx'),      // bespoke 4.4.2 hero (2D): hazards + live/neutral/earth wiring (switch/fuse in live) + earthed/insulated/unearthed fault + fuse-rating/trip choice
    induction_3d:        () => import('./widgets/Induction3D.jsx'),    // bespoke 4.5.1 hero (3D/three.js): magnet moving in/out of a coil + deflecting galvanometer + turns/speed → e.m.f. + Lenz opposing pole
    generator_3d:        () => import('./widgets/Generator3D.jsx'),    // bespoke 4.5.2 hero (3D/three.js): rectangular coil spun between magnet poles + slip rings/brushes + live e.m.f.-time sine trace tied to coil position
    solenoid_field_3d:   () => import('./widgets/SolenoidField3D.jsx'),// bespoke 4.5.3 hero (3D/three.js): straight-wire concentric field (right-hand grip) + solenoid dipole field (N/S like a bar magnet) + reverse-current
    motor_effect_3d:     () => import('./widgets/MotorEffect3D.jsx'),   // bespoke 4.5.4 hero (3D/three.js): force on a conductor F⊥B⊥I (F=I×B live, Fleming LHR) + reverse current/field + parallel-wire attract/repel
    dc_motor_3d:         () => import('./widgets/DcMotor3D.jsx'),       // bespoke 4.5.5 hero (3D/three.js): rotating coil between poles + couple/force arrows + split-ring commutator reversing current each half-turn + turns/current/field sliders
    transformer_lab:     () => import('./widgets/TransformerLab.jsx'), // bespoke 4.5.6 hero (2D): iron core + primary/secondary coils + animated a.c. flux + Vp/Vs=Np/Ns step-up/down + HV-transmission I²R-loss grid
    scope_lab:           () => import('./widgets/ScopeLab.jsx'),       // bespoke 4.6 hero (2D): CRO screen w/ trace (d.c./a.c./sound) + Y-gain (p.d.=div×V/div) + timebase (T=div×time/div, f=1/T) measurement
    rutherford_lab:      () => import('./widgets/RutherfordLab.jsx'),  // bespoke 5.1.1 hero (2D): nuclear atom (tiny +nucleus + orbiting −electrons, empty space) + alpha-scattering (most through/few deflect/rare bounce-back)
    nuclide_lab:         () => import('./widgets/NuclideLab.jsx'),      // bespoke 5.1.2 hero (2D): build nucleus (protons/neutrons) + Z/A/N + nuclide notation A-Z-X + isotopes (same Z diff N) + ion formation (lose/gain electrons)
    detector_lab:        () => import('./widgets/DetectorLab.jsx'),     // bespoke 5.2.1 hero (2D): cloud chamber/spark counter(α) + GM tube(β/γ) + count rate + corrected count rate (measured−background) + background sources
    emission_lab:        () => import('./widgets/EmissionLab.jsx'),     // bespoke 5.2.2 hero (2D): α(2p2n He)/β(fast e⁻)/γ(EM wave) nature + penetration(paper/Al/lead) + ionising + deflection in E/B fields (α↔β opposite, γ straight)
    decay_eq_lab:        () => import('./widgets/DecayEqLab.jsx'),      // bespoke 5.2.3 hero (2D): α/β/γ decay equations in nuclide notation w/ parent→daughter+emitted + A(top)/Z(bottom) balance check
    fission_fusion_lab:  () => import('./widgets/FissionFusionLab.jsx'), // bespoke 5.2.4 hero (2D): fusion (small→large+energy, stars) / fission (n+U-235→2 daughters+2-3n+energy) / chain reaction w/ control-rod absorption slider (sub/critical/supercritical)
    half_life_lab:       () => import('./widgets/HalfLifeLab.jsx'),      // bespoke 5.2.5 hero (2D): decay-curve halving every half-life (sample dots + count-vs-time graph + slider) / carbon-14 dating (fraction→age) / choosing-isotope cards (type+half-life matched to use)
    radiation_safety_lab: () => import('./widgets/RadiationSafetyLab.jsx'), // bespoke 5.2.6 hero (2D): ionising radiation on living cells (death/mutation/cancer) / protection bench w/ time+distance+shielding sliders → live relative-dose meter
    phase_change_lab:    () => import('./widgets/PhaseChangeLab.jsx'),     // bespoke 2.2.3 hero (2D): heating curve w/ melting(0°C)+boiling(100°C) latent-heat plateaux + particle-state box / evaporation bench (surface escape of fast particles → cooling; temp+area+air sliders)
    earth_orbit_lab:     () => import('./widgets/EarthOrbitLab.jsx'),      // bespoke 6.1.1 hero (2D): Earth-Moon-Sun system (365d orbit, tilted 24h spin, ~1mo Moon, ~500s light) / orbital-speed v=2πr/T calculator w/ r+T sliders
    solar_system_lab:    () => import('./widgets/SolarSystemLab.jsx'),     // bespoke 6.1.2 hero (2D): orrery of 8 planets in order + asteroid belt + comet w/ per-planet data (dist/period/density/temp/g/speed) + Sun-pull & velocity arrows / gravity mode (g ∝ mass, falls w/ distance)
    sun_lab:             () => import('./widgets/SunLab.jsx'),             // bespoke 6.2.1 hero (2D): Sun as medium-size star w/ H+He composition + IR/vis/UV radiation spectrum bar / fusion-core mode (H→He+energy, gravity-in vs fusion-out balance)
    star_lifecycle_lab:  () => import('./widgets/StarLifecycleLab.jsx'),  // bespoke 6.2.2 hero (2D): Milky Way galaxy + Sun + light-years / mass-forked star life cycle (cloud→protostar→stable→red giant|supergiant→planetary nebula+white dwarf | supernova+neutron star/black hole) w/ stage slider + mass toggle
    redshift_lab:        () => import('./widgets/RedshiftLab.jsx'),       // bespoke 6.2.3 hero (2D): spectral-line redshift of receding galaxy (lab-ref vs shifted lines + stretched wave, distance→speed slider) / expanding-Universe timeline rewinding billions of galaxies to the Big Bang
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
