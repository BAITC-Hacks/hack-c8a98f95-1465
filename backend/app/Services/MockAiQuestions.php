<?php

namespace App\Services;

class MockAiQuestions
{
    private const QUESTIONS = [
        'context' => 'В какой ситуации возникает проблема и как её решают сейчас?',
        'users' => 'Кто будет пользоваться решением и какие потребности этих пользователей нужно учесть?',
        'materials' => 'Какие данные, примеры и материалы доступны команде? Если их нет, укажите это.',
        'constraints' => 'Какие есть ограничения по срокам, бюджету, технологиям и доступу?',
        'expectedOutcome' => 'Что именно команда должна передать в результате работы?',
        'successCriteria' => 'По каким измеримым признакам вы поймёте, что проблема решена?',
        'contact' => 'Как связаться с заказчиком и в каком формате обсуждать работу?',
    ];

    public function generate(string $description, array $fields = []): array
    {
        $topic = match (true) {
            preg_match('/англ|язык|говорен|english/iu', $description) === 1 => 'language',
            preg_match('/расписан|аудитор|заняти/iu', $description) === 1 => 'schedule',
            preg_match('/библиот|книг|чтени/iu', $description) === 1 => 'library',
            default => 'general',
        };
        $questions = self::QUESTIONS;
        $questions['users'] = match ($topic) {
            'language' => 'Кто будет практиковать язык, каков текущий уровень и какой формат практики нужен?',
            'schedule' => 'Кому нужно расписание и какие группы, преподавателей или аудитории оно должно учитывать?',
            'library' => 'Кому нужен доступ к книгам и какие действия читателей должна поддерживать система?',
            default => $questions['users'],
        };
        // The original description is preserved verbatim as context, never embellished.
        $provided = ['context' => $description];
        foreach ($fields as $field => $value) {
            if (array_key_exists($field, self::QUESTIONS) && is_string($value) && trim($value) !== '') {
                $provided[$field] = $value;
            }
        }
        $missing = array_values(array_diff(array_keys(self::QUESTIONS), array_keys($provided)));
        $selected = array_slice($missing, 0, 7);
        // Even a complete brief gets three verification questions.
        foreach (array_keys(self::QUESTIONS) as $field) {
            if (count($selected) >= 3) {
                break;
            }
            if (! in_array($field, $selected, true)) {
                $selected[] = $field;
            }
        }

        return [
            'mode' => 'mock',
            'topic' => $topic,
            'missingFields' => $missing,
            'questions' => array_map(fn (string $field) => [
                'id' => 'clarify_'.$field,
                'field' => $field,
                'question' => isset($provided[$field]) ? 'Уточните или подтвердите: '.$questions[$field] : $questions[$field],
            ], $selected),
            'suggestedFields' => $provided,
            'notice' => 'Локальная заглушка. Ответы заполняет заказчик; автоматическая публикация и выдуманные факты исключены.',
        ];
    }
}
