<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

/**
 * The single HTTP seam between Laravel and the internal python-ai service.
 * Laravel is the only caller of python-ai; python-ai never reads the DB and
 * only ever receives context this side has already authorized.
 *
 * No error handling here on purpose - exceptions bubble to StudentTutorService,
 * which owns logging and the user-facing failure path.
 */
class TopicalAiClient
{
    /** @return array{ok: bool, answer: string, meta: array} */
    public function tutorChat(array $body): array
    {
        return $this->post('/tutor/chat', $body);
    }

    /** @return array{ok: bool, quiz: array, meta: array} */
    public function tutorQuiz(array $body): array
    {
        return $this->post('/tutor/quiz', $body);
    }

    private function post(string $path, array $body): array
    {
        return Http::withHeaders(['X-Internal-Token' => (string) config('services.python_ai.token')])
            ->timeout((int) config('services.python_ai.timeout', 90))
            ->acceptJson()
            ->post(rtrim((string) config('services.python_ai.url'), '/').$path, $body)
            ->throw()
            ->json();
    }
}
