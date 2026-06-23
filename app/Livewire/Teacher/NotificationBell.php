<?php

namespace App\Livewire\Teacher;

use App\Models\V2\Notification;
use App\Models\V2\Teacher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Teacher notification bell (embedded in the teacher layout header)
|--------------------------------------------------------------------------
| Bounded preview only: caps each tab at 8 and links to the full page for the
| rest. All queries are scoped to the logged-in teacher (notifiable_id).
*/
class NotificationBell extends Component
{
    private const PREVIEW = 8;

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
    public function unreadCount(): int
    {
        return (int) $this->base()->unread()->count();
    }

    #[Computed]
    public function attention()
    {
        // Worst (lowest current %) first — the right 8 to preview.
        return $this->base()->attention()->active()->with('student')
            ->orderByRaw("CAST(JSON_EXTRACT(data, '$.current_avg') AS UNSIGNED) ASC")
            ->limit(self::PREVIEW)->get();
    }

    #[Computed]
    public function attentionCount(): int
    {
        return (int) $this->base()->attention()->active()->count();
    }

    #[Computed]
    public function updates()
    {
        return $this->base()->updates()->latest()->limit(self::PREVIEW)->get();
    }

    #[Computed]
    public function updateCount(): int
    {
        return (int) $this->base()->updates()->count();
    }

    public function markAllRead(): void
    {
        $this->base()->unread()->update(['read_at' => now()]);
    }

    public function markRead(int $id): void
    {
        $this->base()->where('id', $id)->unread()->update(['read_at' => now()]);
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
        return view('livewire.teacher.notification-bell');
    }
}
