<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskEditor
{
    public const FIELDS = [
        'title' => 'title', 'organization' => 'organization', 'region' => 'region',
        'category' => 'category', 'scope' => 'scope', 'context' => 'context',
        'users' => 'users', 'materials' => 'materials', 'constraints' => 'constraints',
        'expectedOutcome' => 'expected_outcome', 'successCriteria' => 'success_criteria', 'contact' => 'contact',
    ];

    public function save(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $confirmed = $task->confirmed_fields ?? [];
            foreach (self::FIELDS as $field => $column) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field] ?? '';
                    if ($value !== $task->{$column}) {
                        $confirmed = array_values(array_diff($confirmed, [$field]));
                        $task->confirmed_at = null;
                    }
                    $task->{$column} = $value;
                }
            }
            if (array_key_exists('confirmedFields', $data)) {
                $confirmed = $data['confirmedFields'];
                if ($confirmed !== ($task->confirmed_fields ?? [])) {
                    $task->confirmed_at = null;
                }
            }
            foreach ($confirmed as $field) {
                if (trim((string) $task->{self::FIELDS[$field]}) === '') {
                    throw ValidationException::withMessages([
                        'confirmedFields' => ["Нельзя подтвердить незаполненное поле: {$field}."],
                    ]);
                }
            }
            $task->confirmed_fields = array_values($confirmed);
            if ($task->status === 'published') {
                $this->validatePublicationFields($task);
            }
            $task->save();

            return $task->refresh();
        });
    }

    public function publish(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $this->validatePublicationFields($task);
            $task->confirmed_fields = array_keys(array_filter(
                TaskScorer::CRITERIA,
                fn (array $criterion) => trim((string) $task->{$criterion['column']}) !== ''
            ));
            $task->confirmed_at = now();
            $task->published_at ??= now();
            $task->status = 'published';
            $task->save();

            return $task->refresh();
        });
    }

    private function validatePublicationFields(Task $task): void
    {
        $errors = [];
        foreach (['title', 'organization', 'region', 'category'] as $field) {
            if (trim((string) $task->{$field}) === '') {
                $errors[$field] = ['Заполните поле перед публикацией.'];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
