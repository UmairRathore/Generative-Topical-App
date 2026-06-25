<?php

namespace App\Livewire\Student;

use App\Models\V2\Notification;
use App\Models\V2\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/*
|--------------------------------------------------------------------------
| Student notifications — full page (overflow destination for the bell)
|--------------------------------------------------------------------------
| One paginated stream of "update" notifications with an all/unread filter.
| Unread rows stay highlighted until the student reads them (no auto mark-all on
| open, unlike the teacher page) so the highlight is meaningful. Scoped to the
| logged-in student.
*/
class NotificationIndex extends Component
{
    use WithPagination;

    public string $filter = 'all';   // all | unread

    public function updated($name): void
    {
        if ($name === 'filter') {
            $this->resetPage();
        }
    }

    private function studentId(): ?int
    {
        return Auth::guard('v2_student')->id();
    }

    private function base()
    {
        return Notification::query()
            ->where('notifiable_type', Student::class)
            ->where('notifiable_id', $this->studentId());
    }

    #[Computed]
    public function unreadCount(): int
    {
        return (int) $this->base()->unread()->count();
    }

    #[Computed]
    public function items()
    {
        return $this->base()
            ->when($this->filter === 'unread', fn ($q) => $q->unread())
            ->latest()
            ->paginate(20);
    }

    public function markRead(int $id): void
    {
        $this->base()->where('id', $id)->unread()->update(['read_at' => now()]);
        unset($this->unreadCount);
    }

    public function markAllRead(): void
    {
        $this->base()->unread()->update(['read_at' => now()]);
        unset($this->unreadCount);
    }

    public function render()
    {
        return view('livewire.student.notification-index');
    }
}
