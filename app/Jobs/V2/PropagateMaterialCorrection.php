<?php

namespace App\Jobs\V2;

use App\Models\V2\QualityPropagation;
use App\Services\V2\PropagationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/*
| Runs one material-error propagation. The work itself is idempotent + resumable
| (see PropagationService::run), so a re-dispatch after a failure safely continues
| from where it stopped. tries=1: we prefer an explicit re-dispatch over silent
| auto-retries.
*/
class PropagateMaterialCorrection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $propagationId) {}

    public function handle(PropagationService $service): void
    {
        $prop = QualityPropagation::find($this->propagationId);

        if ($prop && $prop->status !== 'completed') {
            $service->run($prop);
        }
    }
}
