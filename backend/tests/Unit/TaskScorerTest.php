<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\MockAiQuestions;
use App\Services\TaskScorer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaskScorerTest extends TestCase
{
    public function test_only_filled_confirmed_fields_receive_points(): void
    {
        $task = (new Task)->forceFill(['context' => 'Проблема', 'materials' => 'Данные', 'users' => '   ']);
        $task->confirmed_fields = ['context', 'users'];
        $result = (new TaskScorer)->evaluate($task);
        self::assertSame(20, $result['score']);
        self::assertTrue($result['scoreBreakdown']['context']['confirmed']);
        self::assertFalse($result['scoreBreakdown']['materials']['confirmed']);
        self::assertSame(0, $result['scoreBreakdown']['users']['points']);
        self::assertCount(7, $result['scoreBreakdown']);
    }

    public function test_complete_card_scores_exactly_one_hundred(): void
    {
        $task = new Task;
        foreach (TaskScorer::CRITERIA as $criterion) {
            $task->{$criterion['column']} = 'Подтверждённые сведения';
        }
        $task->confirmed_fields = array_keys(TaskScorer::CRITERIA);
        self::assertSame(100, (new TaskScorer)->evaluate($task)['score']);
    }

    #[DataProvider('boundaries')]
    public function test_readiness_boundaries(int $score, string $level): void
    {
        self::assertSame($level, TaskScorer::level($score)[0]);
    }

    public static function boundaries(): array
    {
        return [[0, 'draft'], [39, 'draft'], [40, 'working'], [69, 'working'], [70, 'ready'], [89, 'ready'], [90, 'priority'], [100, 'priority']];
    }

    public function test_ai_is_deterministic_and_never_invents_card_data(): void
    {
        $service = new MockAiQuestions;
        $description = 'Первокурсникам трудно практиковать английский.';
        $result = $service->generate($description, ['users' => 'Первокурсники']);
        self::assertSame($result, $service->generate($description, ['users' => 'Первокурсники']));
        self::assertGreaterThanOrEqual(3, count($result['questions']));
        self::assertSame('language', $result['topic']);
        self::assertSame(['context' => $description, 'users' => 'Первокурсники'], $result['suggestedFields']);
        self::assertNotContains('users', $result['missingFields']);
        self::assertContains('materials', $result['missingFields']);
    }

    public function test_ai_asks_three_verification_questions_for_a_complete_brief(): void
    {
        $fields = array_fill_keys(array_keys(TaskScorer::CRITERIA), 'Сведения заказчика');
        $result = (new MockAiQuestions)->generate('Проблема', $fields);
        self::assertSame([], $result['missingFields']);
        self::assertCount(3, $result['questions']);
        self::assertSame($fields, $result['suggestedFields']);
    }
}
