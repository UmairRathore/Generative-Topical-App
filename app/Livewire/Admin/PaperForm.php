<?php

namespace App\Livewire\Admin;

use App\Models\Paper;
use App\Models\Subject;
use Illuminate\Support\Str;
use Livewire\Component;

class PaperForm extends Component
{
    public ?Paper $paper = null;

    public ?int $subject_id = null;
    public string $source_file = '';
    public string $paper_code = '';
    public string $session = '';
    public ?string $session_code = null;
    public ?int $year = null;
    public ?int $paper_number = null;
    public ?string $variant = null;
    public string $raw_meta_json = '';

    public function mount(?Paper $paper = null): void
    {
        if ($paper && $paper->exists) {
            $this->paper = $paper;
            $this->subject_id = $paper->subject_id;
            $this->source_file = $paper->source_file;
            $this->paper_code = $paper->paper_code;
            $this->session = $paper->session;
            $this->session_code = $paper->session_code;
            $this->year = $paper->year;
            $this->paper_number = $paper->paper_number;
            $this->variant = $paper->variant;
            $this->raw_meta_json = $paper->raw_meta ? json_encode($paper->raw_meta, JSON_PRETTY_PRINT) : '';
        } else {
            $first = Subject::first();
            $this->subject_id = $first?->id;
        }
    }

    public function save()
    {
        $data = $this->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'source_file' => ['nullable', 'string', 'max:255'],
            'paper_code' => ['required', 'string', 'max:64'],
            'session' => ['required', 'string', 'max:32'],
            'session_code' => ['nullable', 'string', 'max:8'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'paper_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'variant' => ['nullable', 'string', 'max:8'],
            'raw_meta_json' => ['nullable', 'string'],
        ]);

        $rawMeta = [];
        if ($this->raw_meta_json !== '') {
            $decoded = json_decode($this->raw_meta_json, true);
            if (! is_array($decoded)) {
                $this->addError('raw_meta_json', 'Must be valid JSON object.');
                return;
            }
            $rawMeta = $decoded;
        }

        // Generate a stable source_file when admin omits it.
        if (! $this->source_file) {
            $subject = Subject::find($this->subject_id);
            $slug = $subject ? $subject->slug : 'unknown';
            $bits = array_filter([$slug, $this->year, $this->session, $this->variant]);
            $this->source_file = 'manual_'.Str::slug(implode('_', $bits), '_');
        }

        $rawMeta = array_merge($rawMeta, ['source' => 'manual_admin', 'created_from_admin' => true]);

        $payload = [
            'subject_id' => $this->subject_id,
            'paper_code' => $this->paper_code,
            'session' => $this->session,
            'session_code' => $this->session_code ?: null,
            'year' => $this->year,
            'paper_number' => $this->paper_number,
            'variant' => $this->variant ?: null,
            'raw_meta' => $rawMeta,
        ];

        if ($this->paper) {
            $this->paper->forceFill(array_merge(['source_file' => $this->source_file], $payload))->save();
            session()->flash('flash', 'Paper updated.');
            return redirect()->route('admin.papers.questions', $this->paper);
        }

        $paper = Paper::updateOrCreate(
            ['source_file' => $this->source_file],
            $payload,
        );

        session()->flash('flash', 'Paper created.');
        return redirect()->route('admin.papers.questions', $paper);
    }

    public function render()
    {
        return view('livewire.admin.paper-form', [
            'subjects' => Subject::with('qualification.examBoard')->orderBy('name')->get(),
        ])->layout('components.layouts.dashboard');
    }
}
