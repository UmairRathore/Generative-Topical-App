<?php

namespace Tests\Unit\AI;

use App\Services\AI\TutorActionDetector;
use PHPUnit\Framework\TestCase;

/**
 * The pure server-side quiz-intent classifier. Locks the exact trigger /
 * non-trigger contract so it can't silently drift from the React fast-path.
 */
class TutorActionDetectorTest extends TestCase
{
    private TutorActionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new TutorActionDetector();
    }

    /**
     * @dataProvider quizRequests
     */
    public function test_explicit_quiz_commands_are_detected(string $message): void
    {
        $this->assertSame(
            TutorActionDetector::ACTION_QUIZ,
            $this->detector->detect($message),
            "Expected a quiz request: \"$message\""
        );
    }

    public static function quizRequests(): array
    {
        return [
            ['quiz me'],
            ['Quiz me'],
            ['quiz me on this'],
            ['test me on this'],
            ['test me on projectile motion'],
            ['generate a quiz'],
            ['generate a quiz for me'],
            ['create 5 practice questions'],
            ['create me a quiz'],
            ['give me 10 MCQs'],
            ['give me some practice questions'],
            ['start a quiz on this topic'],
            ['make me a quick quiz'],
            ['ask me some questions'],
        ];
    }

    /**
     * @dataProvider chatMessages
     */
    public function test_discussion_and_ambiguous_messages_stay_chat(string $message): void
    {
        $this->assertSame(
            TutorActionDetector::ACTION_CHAT,
            $this->detector->detect($message),
            "Expected chat: \"$message\""
        );
    }

    public static function chatMessages(): array
    {
        return [
            ['explain this quiz'],
            ['why did the quiz mark me wrong?'],
            ['review my quiz'],
            ['what should I study before the quiz?'],
            ['make the previous quiz easier'],
            ['explain question 2 from the quiz'],
            ['what is a quiz?'],
            // Ordinary tutoring questions - must never be hijacked into a quiz.
            ['why is my answer wrong?'],
            ['I do not understand this'],
            ['can you explain projectile motion'],
            ['what is the correct answer'],
            [''],
            ['   '],
        ];
    }
}
