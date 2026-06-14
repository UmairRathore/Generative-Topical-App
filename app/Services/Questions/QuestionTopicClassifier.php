<?php

namespace App\Services\Questions;

use App\Services\Syllabus\SyllabusLoader;

/**
 * Deterministic keyword/rule classifier mapping a question's normalized text
 * to Cambridge AS/A Level Physics syllabus topics, subtopics and learning objectives.
 *
 * Topic IDs and titles are NEVER invented — every output row is anchored
 * to the loaded syllabus tree.
 */
class QuestionTopicClassifier
{
    public const VERSION = 'rule-v1';

    /**
     * Subtopic keyword map.
     * Each entry: subtopic_id => [['kw', weight], ...]
     * Higher weights = stronger evidence. Multi-word phrases score higher than bare words.
     *
     * @var array<string, array<int, array{0:string,1:float}>>
     */
    private const SUBTOPIC_KEYWORDS = [
        // 1 Physical quantities and units
        '1.1' => [
            ['estimate', 1.6], ['order of magnitude', 2.4], ['reasonable estimate', 2.4],
            ['numerical magnitude', 2.0], ['approximate value', 1.4],
        ],
        '1.2' => [
            ['si base unit', 3.0], ['base unit', 2.4], ['base units', 2.4],
            ['si units', 2.0], ['derived unit', 2.4], ['homogeneity', 2.6],
            ['homogeneous', 2.4], ['units of', 1.2],
            ['nano', 2.2], ['micro', 1.6], ['pico', 2.4], ['milli', 1.4],
            ['kilo', 1.0], ['mega', 1.4], ['giga', 1.6], ['tera', 2.0],
            ['prefix', 2.4], ['prefixes', 2.4],
            ['kg m', 1.6], ['kgm', 1.6],
        ],
        '1.3' => [
            ['uncertainty', 3.0], ['uncertainties', 3.0],
            ['percentage uncertainty', 3.4], ['fractional uncertainty', 3.4],
            ['absolute uncertainty', 3.0],
            ['systematic error', 3.0], ['random error', 3.0],
            ['precision', 2.0], ['accuracy', 1.8], ['accurate', 1.0],
            ['zero error', 2.4],
        ],
        '1.4' => [
            ['scalar', 2.6], ['vector', 2.0], ['vectors', 2.0],
            ['resultant', 2.2], ['component', 1.4], ['components', 1.4],
            ['perpendicular component', 2.6], ['coplanar', 2.4],
            ['vector triangle', 2.6], ['add vectors', 2.0], ['vector addition', 2.6],
        ],

        // 2 Kinematics
        '2.1' => [
            ['velocity', 1.8], ['acceleration', 2.2], ['displacement', 2.0],
            ['speed', 1.2], ['kinematic', 2.6], ['kinematics', 3.0],
            ['v^2 = u^2', 3.4], ['v=u+at', 3.0], ['suvat', 3.4],
            ['equations of motion', 3.0],
            ['velocity-time', 2.6], ['velocity time', 2.4],
            ['displacement-time', 2.4], ['distance-time', 2.0],
            ['free fall', 2.6], ['falling object', 2.0],
            ['projectile', 2.8], ['horizontal velocity', 2.2],
            ['gradient of', 1.6], ['area under', 1.6],
        ],

        // 3 Dynamics
        '3.1' => [
            ['newton', 2.6], ["newton's first law", 3.4], ["newton's second law", 3.4],
            ["newton's third law", 3.4], ['f = ma', 3.0], ['f=ma', 3.0],
            ['mass times acceleration', 2.6],
            ['linear momentum', 2.8], ['rate of change of momentum', 3.2],
            ['weight', 1.8], ['gravitational force on a mass', 2.2],
        ],
        '3.2' => [
            ['friction', 2.6], ['drag', 2.4], ['air resistance', 3.0],
            ['terminal velocity', 3.4], ['viscous', 2.4], ['resistive force', 2.4],
            ['constant velocity', 1.6],
        ],
        '3.3' => [
            ['conservation of momentum', 3.4], ['principle of conservation of momentum', 3.6],
            ['elastic collision', 3.0], ['inelastic collision', 3.0],
            ['collision', 2.0], ['explosion', 2.4],
            ['relative speed of approach', 3.0], ['relative speed of separation', 3.0],
            ['impulse', 2.6],
        ],

        // 4 Forces, density and pressure
        '4.1' => [
            ['moment of', 2.4], ['moments', 2.0], ['turning effect', 2.6],
            ['couple', 2.6], ['torque', 2.6], ['pivot', 2.4],
            ['perpendicular distance', 2.4],
        ],
        '4.2' => [
            ['equilibrium', 2.4], ['principle of moments', 3.4],
            ['vector triangle', 2.4], ['no resultant', 2.0],
            ['three forces', 1.8],
        ],
        '4.3' => [
            ['density', 2.0], ['pressure', 2.0], ['hydrostatic', 3.0],
            ['upthrust', 3.0], ['archimedes', 3.0],
            ['rho g h', 3.0], ['p = rho g h', 3.4],
            ['fluid', 1.6], ['liquid column', 2.4],
            ['atmospheric pressure', 2.4],
        ],

        // 5 Work, energy and power
        '5.1' => [
            ['work done', 2.6], ['work = force', 2.4],
            ['conservation of energy', 3.0], ['principle of conservation of energy', 3.4],
            ['efficiency', 2.4], ['useful energy', 2.2],
            ['power', 1.8], ['p = w/t', 3.0], ['p = fv', 3.0],
        ],
        '5.2' => [
            ['kinetic energy', 2.8], ['gravitational potential energy', 3.0],
            ['potential energy', 2.0], ['mgh', 2.6], ['1/2 mv^2', 3.0],
            ['gpe', 2.4],
        ],

        // 6 Deformation of solids
        '6.1' => [
            ['stress', 2.4], ['strain', 2.4], ['young modulus', 3.4],
            ["young's modulus", 3.4], ['hooke', 2.6], ["hooke's law", 3.0],
            ['spring constant', 2.4], ['extension', 1.4], ['compression', 1.6],
            ['limit of proportionality', 3.0],
        ],
        '6.2' => [
            ['elastic deformation', 2.6], ['plastic deformation', 2.8],
            ['elastic limit', 2.6], ['force-extension', 2.4], ['force extension', 2.0],
            ['1/2 fx', 2.4], ['1/2 kx^2', 2.6], ['elastic potential energy', 3.0],
        ],

        // 7 Waves
        '7.1' => [
            ['progressive wave', 3.0], ['wave motion', 2.0],
            ['wavelength', 1.6], ['frequency', 1.4],
            ['phase difference', 2.4],
            ['cathode-ray oscilloscope', 2.6], ['cro', 2.0], ['oscilloscope', 2.0],
            ['time-base', 2.4], ['time base', 2.0], ['y-gain', 2.4],
            ['v = f lambda', 2.6], ['intensity', 1.8], ['amplitude', 1.4],
        ],
        '7.2' => [
            ['transverse wave', 3.0], ['longitudinal wave', 3.0],
            ['transverse', 2.0], ['longitudinal', 2.0],
        ],
        '7.3' => [
            ['doppler', 3.4], ['observed frequency', 2.6],
            ['source moves', 2.0], ['stationary observer', 2.4],
        ],
        '7.4' => [
            ['electromagnetic spectrum', 3.0], ['electromagnetic wave', 2.4],
            ['radio waves', 2.0], ['microwave', 2.0], ['infrared', 2.0],
            ['ultraviolet', 2.0], ['x-ray', 1.6], ['gamma ray', 1.6],
            ['400-700 nm', 2.6], ['visible to the human eye', 2.6],
        ],
        '7.5' => [
            ['polarisation', 3.4], ['polarization', 3.4], ['polarised', 3.0],
            ["malus", 3.0], ["malus's law", 3.4],
            ['polarising filter', 2.6],
        ],

        // 8 Superposition
        '8.1' => [
            ['stationary wave', 3.4], ['standing wave', 3.0],
            ['node', 2.4], ['antinode', 2.6], ['nodes and antinodes', 2.8],
            ['superposition', 2.6], ['principle of superposition', 3.0],
        ],
        '8.2' => [
            ['diffraction', 2.6], ['diffract', 1.8],
        ],
        '8.3' => [
            ['interference', 2.4], ['coherence', 2.6], ['coherent', 2.0],
            ['two-source', 2.6], ['two source interference', 2.6],
            ['double-slit', 3.0], ['double slit', 2.6], ['fringe', 2.4],
            ['lambda = ax/d', 3.0],
        ],
        '8.4' => [
            ['diffraction grating', 3.4], ['grating', 2.4],
            ['d sin theta', 2.6], ['nth order', 2.0],
        ],

        // 9 Electricity
        '9.1' => [
            ['charge carrier', 2.6], ['drift velocity', 2.4],
            ['quantised', 2.0], ['q = it', 2.6],
            ['i = anvq', 2.8], ['number density', 2.4],
            ['current', 0.6], ['flow of charge', 2.0],
        ],
        '9.2' => [
            ['potential difference', 1.8], ['p.d.', 1.0], ['p = vi', 2.6],
            ['p = i^2 r', 2.6], ['p = v^2/r', 2.6],
            ['energy transferred per unit charge', 2.6],
        ],
        '9.3' => [
            ['resistance', 1.6], ['resistivity', 3.0], ['r = rho l/a', 2.8],
            ['ohm', 1.6], ["ohm's law", 2.6],
            ['filament lamp', 2.6], ['i-v characteristic', 2.6],
            ['light-dependent resistor', 2.6], ['ldr', 2.4],
            ['thermistor', 2.6], ['ntc', 2.4],
            ['v = ir', 2.0],
        ],

        // 10 D.C. circuits
        '10.1' => [
            ['e.m.f.', 2.0], ['emf', 2.0], ['electromotive force', 2.6],
            ['internal resistance', 3.0], ['terminal potential difference', 2.4],
            ['cell', 1.0], ['battery', 1.0],
        ],
        '10.2' => [
            ['kirchhoff', 3.0], ["kirchhoff's", 3.4], ['conservation of charge', 2.4],
            ['junction', 1.8], ['series', 1.0], ['parallel', 1.0],
            ['combined resistance', 2.4], ['resistors in series', 2.4],
            ['resistors in parallel', 2.6],
        ],
        '10.3' => [
            ['potential divider', 3.4], ['potentiometer', 3.0],
            ['galvanometer', 2.6], ['null method', 2.6], ['null deflection', 2.6],
        ],

        // 11 Particle physics
        '11.1' => [
            ['nucleus', 2.0], ['nuclei', 2.0], ['nuclide', 2.6],
            ['isotope', 2.6], ['isotopes', 2.6],
            ['proton number', 2.6], ['nucleon number', 2.6],
            ['alpha particle', 2.4], ['alpha-particle', 2.6], ['alpha scattering', 3.0],
            ['beta particle', 2.4], ['beta-particle', 2.4], ['gamma radiation', 2.4],
            ['antiparticle', 2.6], ['positron', 2.6], ['neutrino', 2.4], ['antineutrino', 2.6],
            ['decay equation', 2.6], ['radioactive decay equation', 2.6],
            ['unified atomic mass unit', 2.6],
        ],
        '11.2' => [
            ['quark', 3.0], ['quarks', 3.0],
            ['baryon', 2.6], ['meson', 2.6], ['hadron', 2.6],
            ['lepton', 2.6], ['fundamental particle', 2.6],
            ['up quark', 2.4], ['down quark', 2.4],
        ],

        // 12 Motion in a circle
        '12.1' => [
            ['radian', 2.0], ['angular displacement', 2.4],
            ['angular speed', 2.6], ['angular velocity', 2.4],
            ['omega = 2 pi/t', 2.6], ['v = r omega', 2.6],
        ],
        '12.2' => [
            ['centripetal', 3.0], ['centripetal acceleration', 3.4],
            ['centripetal force', 3.4],
            ['mv^2/r', 3.0], ['m omega^2 r', 2.8],
            ['circular motion', 2.4], ['uniform circular motion', 2.6],
        ],

        // 13 Gravitational fields
        '13.1' => [
            ['gravitational field', 2.6],
            ['field of force', 1.6], ['field lines', 1.4],
        ],
        '13.2' => [
            ["newton's law of gravitation", 3.4], ['law of gravitation', 3.0],
            ['point mass', 2.4], ['gm1 m2', 2.6], ['g m_1 m_2', 2.6],
            ['geostationary', 3.0], ['orbit', 1.8], ['satellite', 2.0],
        ],
        '13.3' => [
            ['gravitational field strength', 2.6],
            ['g = gm/r^2', 2.6], ['g approximately constant', 2.4],
        ],
        '13.4' => [
            ['gravitational potential', 3.0],
            ['phi = -gm/r', 2.8], ['-gmm/r', 2.6],
            ['gravitational potential energy', 2.4],
        ],

        // 14 Temperature
        '14.1' => [
            ['thermal equilibrium', 3.0], ['heat flows from', 1.6],
        ],
        '14.2' => [
            ['thermodynamic temperature', 3.0], ['absolute zero', 2.6],
            ['kelvin', 1.8], ['celsius', 1.6], ['thermocouple', 2.6],
            ['kelvin scale', 2.4],
        ],
        '14.3' => [
            ['specific heat capacity', 3.4], ['specific latent heat', 3.4],
            ['latent heat of fusion', 2.8], ['latent heat of vaporisation', 2.8],
        ],

        // 15 Ideal gases
        '15.1' => [
            ['mole', 2.4], ['avogadro', 3.0], ['molar', 2.0],
        ],
        '15.2' => [
            ['ideal gas', 3.0], ['pv = nrt', 3.4], ['pv=nrt', 3.4],
            ['pv = nkt', 2.8], ['boltzmann constant', 3.0], ['boltzmann', 2.4],
            ['equation of state', 2.8],
        ],
        '15.3' => [
            ['kinetic theory', 3.0], ['kinetic theory of gases', 3.4],
            ['mean square speed', 2.8], ['root-mean-square speed', 3.0], ['r.m.s. speed', 2.6],
            ['1/3 nm', 2.4], ['3/2 kt', 3.0],
        ],

        // 16 Thermodynamics
        '16.1' => [
            ['internal energy', 3.0],
            ['random distribution', 2.0], ['kinetic and potential energies', 2.0],
        ],
        '16.2' => [
            ['first law of thermodynamics', 3.4],
            ['w = p delta v', 2.8], ['delta u = q + w', 3.0],
            ['heating of the system', 2.0], ['work done on the gas', 2.4],
        ],

        // 17 Oscillations
        '17.1' => [
            ['simple harmonic motion', 3.4], ['s.h.m.', 3.0], ['shm', 2.6],
            ['simple harmonic oscillation', 3.4],
            ['a = -omega^2 x', 3.0], ['x = x_0 sin omega t', 2.8],
            ['angular frequency', 2.4],
        ],
        '17.2' => [
            ['energy in simple harmonic', 3.0],
            ['1/2 m omega^2 x_0^2', 2.8],
            ['interchange between kinetic and potential', 2.6],
        ],
        '17.3' => [
            ['damping', 2.8], ['damped', 2.4], ['light damping', 2.6],
            ['critical damping', 2.6], ['heavy damping', 2.6],
            ['resonance', 3.0], ['natural frequency', 2.6], ['forced oscillation', 2.6],
        ],

        // 18 Electric fields
        '18.1' => [
            ['electric field', 2.4], ['force per unit positive charge', 2.6],
            ['f = qe', 2.4],
        ],
        '18.2' => [
            ['uniform electric field', 2.8], ['parallel plates', 2.4],
            ['e = v/d', 2.6], ['e = delta v/delta d', 2.6],
            ['charged particle in', 2.0],
        ],
        '18.3' => [
            ['coulomb', 2.6], ["coulomb's law", 3.4], ['point charge', 2.0],
            ['q1 q2', 2.6], ['4 pi epsilon_0', 2.4],
        ],
        '18.4' => [
            ['field strength due to a point charge', 2.6],
            ['e = q/(4 pi epsilon_0 r^2)', 2.8],
        ],
        '18.5' => [
            ['electric potential', 2.8], ['potential gradient', 2.4],
            ['v = q/(4 pi epsilon_0 r)', 2.6],
            ['electric potential energy', 2.4],
        ],

        // 19 Capacitance
        '19.1' => [
            ['capacitance', 3.0], ['capacitor', 2.4],
            ['c = q/v', 2.4], ['parallel plate capacitor', 2.6],
            ['capacitors in series', 2.6], ['capacitors in parallel', 2.6],
        ],
        '19.2' => [
            ['energy stored in a capacitor', 3.0],
            ['1/2 cv^2', 2.6], ['1/2 qv', 2.4],
            ['potential-charge graph', 2.6],
        ],
        '19.3' => [
            ['discharging a capacitor', 3.0],
            ['time constant', 2.8], ['tau = rc', 2.8], ['rc', 1.4],
            ['exponential decay', 2.4], ['e^(-t/rc)', 2.8],
        ],

        // 20 Magnetic fields
        '20.1' => [
            ['magnetic field', 1.6], ['magnetic field lines', 2.0],
        ],
        '20.2' => [
            ['current-carrying conductor', 2.6],
            ['f = bil', 2.8], ['bil sin', 2.8],
            ["fleming's left-hand rule", 2.8], ['flemings left hand', 2.4],
            ['magnetic flux density', 2.8],
        ],
        '20.3' => [
            ['moving charge', 2.0], ['f = bqv', 2.8],
            ['hall voltage', 3.0], ['hall probe', 2.8],
            ['velocity selection', 2.8], ['velocity selector', 2.8],
        ],
        '20.4' => [
            ['solenoid', 2.6], ['ferrous core', 2.4],
            ['parallel conductor', 2.4], ['flat circular coil', 2.4],
        ],
        '20.5' => [
            ['electromagnetic induction', 3.4], ['magnetic flux linkage', 2.8],
            ['phi = ba', 2.4], ["faraday's law", 3.0], ["lenz's law", 3.0],
            ['induced e.m.f.', 2.8], ['induced emf', 2.4],
            ['changing magnetic flux', 2.8],
        ],

        // 21 Alternating currents
        '21.1' => [
            ['alternating current', 2.6], ['a.c.', 1.4], ['sinusoidal', 2.4],
            ['peak value', 2.0], ['root-mean-square', 2.4], ['r.m.s.', 2.0],
            ['rms', 1.6], ['i_0/sqrt 2', 2.6],
        ],
        '21.2' => [
            ['rectification', 3.0], ['half-wave rectification', 2.8],
            ['full-wave rectification', 2.8],
            ['bridge rectifier', 2.8], ['smoothing', 2.4],
            ['four diodes', 2.4],
        ],

        // 22 Quantum physics
        '22.1' => [
            ['photon', 2.4], ['quantum of electromagnetic', 2.8],
            ['e = hf', 2.6], ['electronvolt', 2.4], ['ev as a unit', 2.0],
            ['p = e/c', 2.6], ['photon momentum', 2.6],
        ],
        '22.2' => [
            ['photoelectric', 3.0], ['photoelectric effect', 3.4],
            ['threshold frequency', 2.8], ['threshold wavelength', 2.6],
            ['work function', 3.0], ['hf = phi', 2.6],
            ['maximum kinetic energy of photoelectrons', 2.8],
        ],
        '22.3' => [
            ['wave-particle duality', 3.0], ['wave particle duality', 3.0],
            ['de broglie', 3.4], ['lambda = h/p', 2.8],
            ['electron diffraction', 2.6],
        ],
        '22.4' => [
            ['energy level', 2.6], ['line spectra', 2.6], ['line spectrum', 2.6],
            ['emission spectra', 2.4], ['absorption spectra', 2.4],
            ['hf = e1 - e2', 2.6], ['discrete electron energy', 2.6],
        ],

        // 23 Nuclear physics
        '23.1' => [
            ['mass defect', 3.0], ['binding energy', 3.0],
            ['binding energy per nucleon', 3.0],
            ['nuclear fusion', 2.6], ['nuclear fission', 2.6],
            ['e = mc^2', 2.6], ['e = c^2 delta m', 2.6],
        ],
        '23.2' => [
            ['decay constant', 3.0], ['activity', 1.6], ['a = lambda n', 2.6],
            ['half-life', 2.8], ['half life', 2.8],
            ['exponential decay', 2.4], ['lambda = 0.693', 2.6],
        ],

        // 24 Medical physics
        '24.1' => [
            ['ultrasound', 3.0], ['piezoelectric', 3.0], ['piezo-electric', 3.0],
            ['acoustic impedance', 2.8],
            ['intensity reflection coefficient', 2.6],
            ['attenuation of ultrasound', 2.6],
        ],
        '24.2' => [
            ['x-ray', 2.6], ['x-rays', 2.6], ['xray imaging', 2.6],
            ['ct scan', 2.8], ['computed tomography', 2.8],
            ['contrast in x-ray', 2.4], ['attenuation of x-rays', 2.6],
            ['minimum wavelength of x-rays', 2.6],
        ],
        '24.3' => [
            ['pet scan', 3.0], ['positron emission tomography', 3.4],
            ['tracer', 2.4], ['annihilation', 2.6],
            ['gamma-ray photons', 2.4],
        ],

        // 25 Astronomy and cosmology
        '25.1' => [
            ['standard candle', 3.0], ['luminosity', 2.6],
            ['radiant flux', 2.6], ['inverse square law', 2.4],
            ['l/(4 pi d^2)', 2.6],
        ],
        '25.2' => [
            ["wien's displacement", 3.0], ['wien displacement', 2.6],
            ['stefan-boltzmann', 3.0], ['stefan boltzmann', 3.0],
            ['l = 4 pi sigma r^2 t^4', 2.8],
        ],
        '25.3' => [
            ["hubble's law", 3.4], ['hubble law', 3.0], ['hubble', 2.4],
            ['big bang', 3.0], ['redshift', 3.0], ['red shift', 2.8],
            ['expanding universe', 2.4],
        ],
    ];

    /**
     * Counter-keywords: signals that should *down-weight* a subtopic when
     * the language overlaps with another topic. Helps disambiguate.
     *
     * @var array<string, array<int, array{0:string,1:float}>>
     */
    private const SUBTOPIC_NEGATIVE_KEYWORDS = [
        '20.1' => [['electric field', 1.6]],
        '18.1' => [['magnetic field', 1.6]],
    ];

    /** @var array<int,array<string,mixed>> */
    private array $syllabusIndex = [];

    /** @var array<string,array<string,mixed>> Subtopic id => meta */
    private array $subtopicMeta = [];

    public function __construct(private readonly QuestionTextNormalizer $normalizer) {}

    /**
     * Load a syllabus tree (decoded JSON array) into the classifier.
     */
    public function loadSyllabus(array $data): void
    {
        $loader = new SyllabusLoader();
        $this->syllabusIndex = $loader->flattenFromArray($data);

        $this->subtopicMeta = [];
        foreach ($data['topics'] ?? [] as $topic) {
            foreach ($topic['subtopics'] ?? [] as $sub) {
                $this->subtopicMeta[(string) $sub['id']] = [
                    'topic_id' => (string) $topic['id'],
                    'topic_title' => (string) $topic['title'],
                    'subtopic_id' => (string) $sub['id'],
                    'subtopic_title' => (string) $sub['title'],
                    'level' => (string) ($topic['level'] ?? 'AS'),
                    'objectives' => $sub['learning_objectives'] ?? [],
                ];
            }
        }
    }

    /**
     * Classify one question.
     *
     * @return array<int,array<string,mixed>> Up to 3 ranked topic tags.
     */
    public function classify(array $question): array
    {
        $blob = $this->normalizer->blob($question);
        if ($blob === '') {
            return [];
        }

        $rawScores = $this->scoreSubtopics($blob);
        if (empty($rawScores)) {
            return [];
        }

        arsort($rawScores);
        $maxRaw = reset($rawScores) ?: 0.0;
        if ($maxRaw <= 0) {
            return [];
        }

        $topRaw = array_slice($rawScores, 0, 3, true);
        $tags = [];
        $primaryAssigned = false;
        $secondScore = null;

        $idx = 0;
        foreach ($topRaw as $subId => $rawScore) {
            $idx++;
            if ($idx === 2) {
                $secondScore = $rawScore;
            }
            $relScore = max(0.0, min(1.0, $rawScore / max($maxRaw, 1e-6)));
            $absScore = $this->squashAbsolute($rawScore);
            // Final blended score: emphasize relative dominance for primary,
            // absolute strength for secondary tags so we don't crown weak winners.
            $finalScore = round(0.6 * $absScore + 0.4 * $relScore, 3);

            // Only the top-1 gets the chance to be "high"; secondaries cap at "medium".
            $relevance = match (true) {
                ! $primaryAssigned && $finalScore >= 0.65 => 'high',
                $finalScore >= 0.45 => 'medium',
                default => 'low',
            };
            if (! $primaryAssigned && $relevance === 'high') {
                $primaryAssigned = true;
            }

            // Secondary tags only kept if absolute score meaningfully high.
            if ($idx > 1 && $absScore < 0.35) {
                continue;
            }

            $meta = $this->subtopicMeta[$subId];
            $lo = $this->matchLearningObjective($blob, $meta['objectives']);

            $reason = $this->buildReason($blob, $subId);
            $tag = [
                'topic_id' => $meta['topic_id'],
                'topic_title' => $meta['topic_title'],
                'subtopic_id' => $meta['subtopic_id'],
                'subtopic_title' => $meta['subtopic_title'],
                'level_2_id' => $lo['number'] ?? null,
                'level_2_title' => $lo['text'] ?? null,
                'relevance' => $relevance,
                'score' => $finalScore,
                'reason' => $reason,
                'needs_review' => false,
            ];

            // Mark needs_review when we are not confident in the LO.
            if ($lo === null && $relevance !== 'low') {
                $tag['needs_review'] = true;
            }

            $tags[] = $tag;
        }

        // If top-1 score is too low (< 0.35) the whole classification is shaky.
        if (! empty($tags) && $tags[0]['score'] < 0.35) {
            $tags[0]['needs_review'] = true;
            $tags[0]['relevance'] = 'low';
        }

        // If top-1 and top-2 are tightly contested, flag the primary for review.
        if ($secondScore !== null && $maxRaw > 0) {
            $gap = ($maxRaw - $secondScore) / $maxRaw;
            if ($gap < 0.15) {
                $tags[0]['needs_review'] = true;
            }
        }

        return $tags;
    }

    /**
     * @return array<string,float> subtopic_id => raw weighted score
     */
    private function scoreSubtopics(string $blob): array
    {
        $scores = [];
        foreach (self::SUBTOPIC_KEYWORDS as $subId => $entries) {
            $score = 0.0;
            foreach ($entries as [$kw, $w]) {
                if ($this->blobContains($blob, $kw)) {
                    $score += $w;
                }
            }
            if ($score > 0) {
                $scores[$subId] = $score;
            }
        }
        foreach (self::SUBTOPIC_NEGATIVE_KEYWORDS as $subId => $entries) {
            if (! isset($scores[$subId])) {
                continue;
            }
            foreach ($entries as [$kw, $w]) {
                if ($this->blobContains($blob, $kw)) {
                    $scores[$subId] -= $w;
                }
            }
            if ($scores[$subId] <= 0) {
                unset($scores[$subId]);
            }
        }
        return $scores;
    }

    private function blobContains(string $blob, string $needle): bool
    {
        // Already normalized to lowercase, so a substring check is enough,
        // but require word boundaries for very short tokens to avoid false hits.
        if (strlen($needle) <= 3) {
            return (bool) preg_match('/\b'.preg_quote($needle, '/').'\b/u', $blob);
        }
        return str_contains($blob, $needle);
    }

    private function squashAbsolute(float $raw): float
    {
        // Map a raw weighted score to a 0..1 confidence using a soft cap.
        // 6.0 worth of keyword weight -> ~0.86 confidence.
        return round(1.0 - exp(-$raw / 4.0), 3);
    }

    /**
     * @param array<int,array<string,mixed>> $objectives
     * @return array{number:int,text:string}|null
     */
    private function matchLearningObjective(string $blob, array $objectives): ?array
    {
        $best = null;
        $bestScore = 0;
        foreach ($objectives as $lo) {
            $text = $this->normalizer->normalize((string) ($lo['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            // Score by counting overlap of meaningful tokens in the LO text.
            $tokens = preg_split('/[^a-z0-9]+/u', $text) ?: [];
            $score = 0;
            $hits = [];
            foreach ($tokens as $tok) {
                if (strlen($tok) < 5) {
                    continue;
                }
                if (in_array($tok, $hits, true)) {
                    continue;
                }
                if (str_contains($blob, $tok)) {
                    $hits[] = $tok;
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'number' => (int) ($lo['id'] ?? 0),
                    'text' => (string) ($lo['text'] ?? ''),
                ];
            }
        }
        // Need at least 2 distinct keyword hits to confidently pick an LO.
        return $bestScore >= 2 ? $best : null;
    }

    private function buildReason(string $blob, string $subId): string
    {
        $hits = [];
        foreach (self::SUBTOPIC_KEYWORDS[$subId] ?? [] as [$kw, $w]) {
            if ($this->blobContains($blob, $kw)) {
                $hits[] = $kw;
            }
            if (count($hits) >= 4) {
                break;
            }
        }
        if (empty($hits)) {
            return 'No strong keyword evidence; assigned by default ranking.';
        }
        return 'Matched syllabus keywords: '.implode(', ', $hits).'.';
    }
}
