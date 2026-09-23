<?php

namespace App\Services;

use App\Models\Task;

class TaskScorer
{
    public const CRITERIA = [
        'context' => ['column' => 'context', 'label' => 'Контекст и потребность', 'points' => 20, 'hint' => 'Опишите проблему и почему её нужно решить.'],
        'materials' => ['column' => 'materials', 'label' => 'Данные и материалы', 'points' => 20, 'hint' => 'Укажите доступные данные и материалы или явно сообщите, что их нет.'],
        'expectedOutcome' => ['column' => 'expected_outcome', 'label' => 'Ожидаемый результат', 'points' => 15, 'hint' => 'Опишите, что команда должна передать заказчику.'],
        'successCriteria' => ['column' => 'success_criteria', 'label' => 'Критерии успеха', 'points' => 15, 'hint' => 'Укажите, как будете проверять результат.'],
        'constraints' => ['column' => 'constraints', 'label' => 'Ограничения', 'points' => 10, 'hint' => 'Укажите сроки, бюджет, технические ограничения или их отсутствие.'],
        'users' => ['column' => 'users', 'label' => 'Пользователи', 'points' => 10, 'hint' => 'Укажите, кто будет пользоваться решением.'],
        'contact' => ['column' => 'contact', 'label' => 'Связь с заказчиком', 'points' => 10, 'hint' => 'Добавьте контакт и формат взаимодействия.'],
    ];

    public function evaluate(Task $task): array
    {
        $breakdown = [];
        $confirmed = $task->confirmed_fields ?? [];
        foreach (self::CRITERIA as $field => $criterion) {
            $filled = trim((string) $task->{$criterion['column']}) !== '';
            $isConfirmed = $filled && in_array($field, $confirmed, true);
            $breakdown[$field] = [
                'label' => $criterion['label'],
                'points' => $isConfirmed ? $criterion['points'] : 0,
                'maxPoints' => $criterion['points'],
                'filled' => $filled,
                'confirmed' => $isConfirmed,
                'hint' => ! $filled ? $criterion['hint'] : ($isConfirmed ? 'Поле заполнено и подтверждено.' : 'Подтвердите сведения в этом поле.'),
            ];
        }
        $score = array_sum(array_column($breakdown, 'points'));
        [$level, $label] = self::level($score);

        return ['score' => $score, 'readinessLevel' => $level, 'readinessLabel' => $label, 'scoreBreakdown' => $breakdown];
    }

    public static function level(int $score): array
    {
        return match (true) {
            $score < 40 => ['draft', 'Требует уточнения'],
            $score < 70 => ['working', 'Рабочая'],
            $score < 90 => ['ready', 'Готовая'],
            default => ['priority', 'Приоритетная'],
        };
    }
}
