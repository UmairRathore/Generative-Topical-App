<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Subject;
use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:register-syllabus-source
|--------------------------------------------------------------------------
| Registers an official syllabus document as a canonical, version-identified
| source artifact (sha256-pinned PDF + optional extracted text). This row is
| the provenance root for learning objectives and authoring policies.
|
|   php artisan v2:register-syllabus-source --subject=5054 --version-label=2023-2025 \
|       --pdf=storage/syllabus/physics/OLevels/5054_2023_2025/5054_y25_sy.pdf \
|       --text=storage/syllabus/physics/OLevels/5054_2023_2025/extracted/5054_y25_sy.txt \
|       --title="Cambridge O Level Physics 5054 syllabus for 2023, 2024 and 2025" \
|       --years=2023,2024,2025
|
| Re-running refreshes paths/hashes for the same (code, version) pair.
*/
class RegisterSyllabusSource extends Command
{
    // NOTE: the option is --version-label (not --version) because --version is a
    // reserved Symfony console global that would short-circuit the command.
    protected $signature = 'v2:register-syllabus-source
        {--subject= : Subject code in v2_subjects (e.g. 5054)}
        {--version-label= : Version label, the exam window (e.g. 2023-2025)}
        {--pdf= : Path to the official syllabus PDF (repo-relative or absolute)}
        {--text= : Path to the page-marked extracted text (optional at registration)}
        {--title= : Official document title}
        {--years= : Comma-separated exam years (e.g. 2023,2024,2025)}
        {--extraction-tool= : Tool provenance for the extracted text (e.g. "pypdf 6.14.2 via tools/extract_pdf_text.py")}
        {--notes= : Free-text registration notes}';

    protected $description = 'Register an official syllabus PDF (+ extracted text) as a canonical V2 syllabus source';

    public function handle(): int
    {
        foreach (['subject', 'version-label', 'pdf', 'title'] as $required) {
            if (! $this->option($required)) {
                $this->error("--{$required} is required.");

                return self::FAILURE;
            }
        }

        $subject = Subject::where('code', $this->option('subject'))->first();
        if (! $subject) {
            $this->error("Subject {$this->option('subject')} not found in v2_subjects. Run V2SubjectsSeeder.");

            return self::FAILURE;
        }

        $pdfPath = $this->resolvePath($this->option('pdf'));
        if (! $pdfPath) {
            $this->error("Syllabus PDF not found: {$this->option('pdf')}");

            return self::FAILURE;
        }

        $textPath = null;
        if ($this->option('text')) {
            $textPath = $this->resolvePath($this->option('text'));
            if (! $textPath) {
                $this->error("Extracted text not found: {$this->option('text')}");

                return self::FAILURE;
            }
        }

        $years = collect(explode(',', (string) $this->option('years')))
            ->map(fn ($y) => (int) trim($y))
            ->filter()
            ->values()
            ->all();

        $source = SyllabusSource::updateOrCreate(
            [
                'syllabus_code' => $subject->code,
                'version_label' => $this->option('version-label'),
            ],
            [
                'subject_id' => $subject->id,
                'title' => $this->option('title'),
                'exam_years' => $years ?: null,
                'source_pdf_path' => $this->repoRelative($pdfPath),
                'source_pdf_sha256' => hash_file('sha256', $pdfPath),
                'extracted_text_path' => $textPath ? $this->repoRelative($textPath) : null,
                'extracted_text_sha256' => $textPath ? hash_file('sha256', $textPath) : null,
                'extraction_tool' => $this->option('extraction-tool'),
                'notes' => $this->option('notes'),
            ]
        );

        $this->info("Registered syllabus source #{$source->id} ({$source->reference()}).");
        $this->table(['field', 'value'], [
            ['subject', "{$subject->name} ({$subject->code}, {$subject->level})"],
            ['pdf', $source->source_pdf_path],
            ['pdf sha256', $source->source_pdf_sha256],
            ['text', $source->extracted_text_path ?? '—'],
            ['text sha256', $source->extracted_text_sha256 ?? '—'],
        ]);

        if (! $textPath) {
            $this->warn('No extracted text registered yet. Produce it with storage/syllabus/tools/extract_pdf_text.py, then re-run with --text=.');
        }

        return self::SUCCESS;
    }

    /** Resolve repo-relative or absolute path to a readable file, or null. */
    private function resolvePath(string $path): ?string
    {
        foreach ([$path, base_path($path)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Store paths repo-relative so rows survive checkouts on other machines. */
    private function repoRelative(string $absolute): string
    {
        $base = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($absolute, $base)
            ? str_replace('\\', '/', substr($absolute, strlen($base)))
            : str_replace('\\', '/', $absolute);
    }
}
