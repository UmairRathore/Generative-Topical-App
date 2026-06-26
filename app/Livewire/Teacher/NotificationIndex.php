<?php

namespace App\Livewire\Teacher;

use App\Models\V2\Notification;
use App\Models\V2\Subject;
use App\Models\V2\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/*
|--------------------------------------------------------------------------
| Teacher notifications - full page (overflow destination for the bell)
|--------------------------------------------------------------------------
| Two tabs, paginated, with class + subject filters and an open/resolved toggle
| on the attention tab. All queries scoped to the logged-in teacher.
*/
class NotificationIndex extends Component
{
    use WithPagination;

    public string $tab = 'attention';   // attention | updates
    public string $status = 'open';      // open | resolved (attention only)
    public string $read = 'all';         // all | unread (updates only)
    public ?int $classId = null;
    public ?int $subjectId = null;

    public function mount(): void
    {
        // Open on the tab the bell was showing when "View all" was clicked.
        $t = request('tab');
        if (in_array($t, ['attention', 'updates'], true)) {
            $this->tab = $t;
        }
    }

    public function updated($name): void
    {
        if (in_array($name, ['tab', 'status', 'read', 'classId', 'subjectId'], true)) {
            $this->resetPage();
        }
    }

    private function teacherId(): ?int
    {
        return Auth::guard('v2_teacher')->id();
    }

    private function base()
    {
        return Notification::query()
            ->where('notifiable_type', Teacher::class)
            ->where('notifiable_id', $this->teacherId());
    }

    #[Computed]
    public function classes()
    {
        $teacher = Auth::guard('v2_teacher')->user();

        return $teacher ? $teacher->classes()->orderBy('name')->get() : collect();
    }

    #[Computed]
    public function subjects()
    {
        $ids = $this->base()->attention()->whereNotNull('subject_id')->distinct()->pluck('subject_id');

        return Subject::whereIn('id', $ids)->orderBy('name')->get();
    }

    #[Computed]
    public function items()
    {
        $q = $this->base()->with('student');

        if ($this->tab === 'attention') {
            $q->attention();
            $this->status === 'resolved' ? $q->whereNotNull('resolved_at') : $q->active();

            if ($this->subjectId) {
                $q->where('subject_id', $this->subjectId);
            }
            if ($this->classId) {
                $q->whereIn('student_id', DB::table('v2_student_enrollments')
                    ->where('class_id', $this->classId)->pluck('student_id'));
            }

            $q->orderByRaw("CAST(JSON_EXTRACT(data, '$.current_avg') AS UNSIGNED) ASC");
        } else {
            $q->updates();
            if ($this->read === 'unread') {
                $q->unread();
            }
            $q->latest();
        }

        return $q->paginate(20);
    }

    #[Computed]
    public function unreadUpdateCount(): int
    {
        return (int) $this->base()->updates()->unread()->count();
    }

    public function markRead(int $id): void
    {
        $this->base()->where('id', $id)->unread()->update(['read_at' => now()]);
    }

    public function markUnread(int $id): void
    {
        $this->base()->where('id', $id)->whereNotNull('read_at')->update(['read_at' => null]);
    }

    public function markAllRead(): void
    {
        $this->base()->unread()->update(['read_at' => now()]);
    }

    public function markHandled(int $id): void
    {
        $this->base()->where('id', $id)->update(['resolved_at' => now(), 'read_at' => now()]);
    }

    public function snooze(int $id): void
    {
        $this->base()->where('id', $id)->update(['snoozed_until' => now()->addDays(7), 'read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.teacher.notification-index');
    }
}
