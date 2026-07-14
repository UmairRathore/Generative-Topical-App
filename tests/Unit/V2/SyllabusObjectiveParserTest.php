<?php

namespace Tests\Unit\V2;

use App\Services\V2\SyllabusObjectiveParser;
use PHPUnit\Framework\TestCase;

/**
 * Guards the deterministic syllabus-text parser: page-furniture stripping,
 * the bare-number objective layout (as in 5054 section 1.1), (a)(b)(c)
 * sub-items, "continued" page-break headings, topic-boundary disambiguation,
 * and the guards that stop prose from opening fake sections/objectives.
 * Pure unit test - no Laravel container, no database.
 */
class SyllabusObjectiveParserTest extends TestCase
{
    private function fixtureText(): string
    {
        return <<<'TXT'
===== PAGE 1 =====
Version 1
Syllabus front matter that must be ignored.
===== PAGE 12 =====
Cambridge O Level Physics 5054 syllabus for 2023, 2024 and 2025.
10
www.cambridgeinternational.org/olevel Back to contents page
3 Subject content
Preamble about practical work that carries no objectives.
1 Motion, forces and energy
1.1 Physical quantities and measurement techniques
1
 Describe how to measure a variety of lengths
2
 Describe how to use a measuring cylinder
1.2 Motion
1 Define speed as distance travelled per unit time
2 Recall and use the equation
 speed = distance
time
 v = s
t
3 Determine from the shape of a distance–time graph when an object is:
(a) at rest
(b) moving with constant speed
bestexamhelp.com
===== PAGE 13 =====
Cambridge O Level Physics 5054 syllabus for 2023, 2024 and 2025. Subject content
11
www.cambridgeinternational.org/olevelBack to contents page
1.2 Motion continued
4 Know that a force of
1.5 N acting on a wrapped line must not open a section
5 State that g is approximately constant (an aside (NTC only) must not open a sub-item)
2 Thermal physics
2.1 Kinetic particle model of matter
2.1.1 States of matter
1 Know the distinguishing properties of solids, liquids and gases
4 Details of the assessment
This is past the end and must be ignored.
TXT;
    }

    public function test_parses_sections_topics_objectives_and_sub_items(): void
    {
        $parsed = (new SyllabusObjectiveParser)->parse($this->fixtureText());

        // Topics recognised at both boundaries (after a heading and after an open objective).
        $this->assertSame([1 => 'Motion, forces and energy', 2 => 'Thermal physics'], $parsed['topics']);

        $codes = array_column($parsed['sections'], 'code');
        $this->assertSame(['1.1', '1.2', '2.1', '2.1.1'], $codes);

        // 2.1 is an intermediate header (non-leaf, no objectives); 2.1.1 is its leaf.
        $byCode = array_column($parsed['sections'], null, 'code');
        $this->assertFalse($byCode['2.1']['leaf']);
        $this->assertSame(0, $byCode['2.1']['lo_count']);
        $this->assertTrue($byCode['2.1.1']['leaf']);

        $this->assertSame(3, $parsed['totals']['leaf_sections']);
        $this->assertSame(2 + 5 + 1, $parsed['totals']['objectives']);
        $this->assertSame([], $parsed['warnings']);
    }

    public function test_bare_number_objective_layout_is_joined(): void
    {
        $parsed = (new SyllabusObjectiveParser)->parse($this->fixtureText());

        $section = array_column($parsed['sections'], null, 'code')['1.1'];
        $this->assertSame('Describe how to measure a variety of lengths', $section['objectives'][0]['text']);
        $this->assertSame(2, $section['objectives'][1]['number']);
    }

    public function test_linearized_equation_lines_join_the_open_objective(): void
    {
        $parsed = (new SyllabusObjectiveParser)->parse($this->fixtureText());

        $motion = array_column($parsed['sections'], null, 'code')['1.2'];
        // The stacked fraction arrives as extra lines - they must join objective 2,
        // not become new objectives ("time" / "t" lines) or sections.
        $this->assertSame(
            'Recall and use the equation speed = distance time v = s t',
            $motion['objectives'][1]['text']
        );
    }

    public function test_sub_items_capture_and_prose_parentheses_do_not_open_sub_items(): void
    {
        $parsed = (new SyllabusObjectiveParser)->parse($this->fixtureText());
        $motion = array_column($parsed['sections'], null, 'code')['1.2'];

        $this->assertSame(
            [['key' => 'a', 'text' => 'at rest'], ['key' => 'b', 'text' => 'moving with constant speed']],
            $motion['objectives'][2]['sub_items']
        );

        // "(NTC only)" sits mid-prose in objective 5 and must stay in its text.
        $this->assertStringContainsString('(NTC only)', $motion['objectives'][4]['text']);
        $this->assertSame([], $motion['objectives'][4]['sub_items']);
    }

    public function test_continued_heading_reopens_section_and_prose_never_opens_sections(): void
    {
        $parsed = (new SyllabusObjectiveParser)->parse($this->fixtureText());
        $motion = array_column($parsed['sections'], null, 'code')['1.2'];

        // "1.2 Motion continued" resumed numbering at 4 across the page break...
        $this->assertSame([1, 2, 3, 4, 5], array_column($motion['objectives'], 'number'));
        // ...and the wrapped "1.5 N acting on..." line joined objective 4 instead of
        // opening a phantom section 1.5.
        $this->assertStringContainsString('1.5 N acting on a wrapped line', $motion['objectives'][3]['text']);
        $this->assertArrayNotHasKey('1.5', array_column($parsed['sections'], null, 'code'));

        // Page furniture (printed page numbers, site line, watermark) left no trace.
        $this->assertStringNotContainsString('bestexamhelp', json_encode($parsed));
        $this->assertStringNotContainsString('cambridgeinternational', json_encode($parsed));
    }

    public function test_provenance_records_pdf_pages(): void
    {
        $parsed = (new SyllabusObjectiveParser)->parse($this->fixtureText());
        $motion = array_column($parsed['sections'], null, 'code')['1.2'];

        $this->assertSame([12], $motion['objectives'][0]['pdf_pages']);   // objective 1 on page 12
        $this->assertSame([13], $motion['objectives'][3]['pdf_pages']);   // objective 4 after the break
        $this->assertSame([12, 13], $motion['pdf_pages']);                // section spans both
    }

    /**
     * Integration guard against the real registered artifact: the official
     * 5054 (2023-2025) structure is 77 sections / 63 leaves / 270 objectives.
     * Skipped when the storage artifact is absent (e.g. fresh checkout).
     */
    public function test_real_5054_artifact_parses_to_official_totals(): void
    {
        $path = __DIR__.'/../../../storage/syllabus/physics/OLevels/5054_2023_2025/extracted/5054_y25_sy.txt';
        if (! is_file($path)) {
            $this->markTestSkipped('5054 extracted-text artifact not present.');
        }

        $parsed = (new SyllabusObjectiveParser)->parse(file_get_contents($path));

        $this->assertSame(77, $parsed['totals']['sections']);
        $this->assertSame(63, $parsed['totals']['leaf_sections']);
        $this->assertSame(270, $parsed['totals']['objectives']);
        $this->assertSame(
            ['Motion, forces and energy', 'Thermal physics', 'Waves', 'Electricity and magnetism', 'Nuclear physics', 'Space physics'],
            array_values($parsed['topics'])
        );
        $this->assertSame([], $parsed['warnings']);

        // Spot-check the demo slice: 1.2 Motion carries exactly 13 objectives.
        $motion = array_column($parsed['sections'], null, 'code')['1.2'];
        $this->assertSame(13, $motion['lo_count']);
    }
}
