<?php

namespace App\Livewire\Student;

use App\Models\V2\Notification;
use App\Models\V2\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Student notification bell (embedded in the student layout header)
|--------------------------------------------------------------------------
| Students get "update" notifications only (exam released/scheduled, due today,
| results released, missed). Bounded preview of the latest 10; the full page
| holds the rest. All queries scoped to the logged-in student (notifiable_id).
*/
class NotificationBell extends Component
{
    private const PREVIEW = 10;

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
    public function totalCount(): int
    {
        return (int) $this->base()->count();
    }

    #[Computed]
    public function items()
    {
        return $this->base()->latest()->limit(self::PREVIEW)->get();
    }

    public function markAllRead(): void
    {
        $this->base()->unread()->update(['read_at' => now()]);
    }

    public function markRead(int $id): void
    {
        $this->base()->where('id', $id)->unread()->update(['read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.student.notification-bell');
    }
}
