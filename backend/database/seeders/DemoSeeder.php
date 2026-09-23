<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Task;
use App\Models\Team;
use App\Services\TaskEditor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $teamNames = ['Steppe Coders', 'Qadam', 'Bilim Lab', 'Orken', 'Campus Makers'];
            foreach ($teamNames as $index => $name) {
                if (! Team::find($index + 1)) {
                    $team = new Team([
                        'name' => $name,
                        'organization' => $index % 2 === 0 ? 'Демо университет «Алем»' : 'Демо колледж «Самғау»',
                        'interests' => ['Образование', 'Веб-приложения'],
                        'skills' => ['Разработка', 'Исследование пользователей'],
                        'technologies' => ['Vue 3', 'PHP', 'SQLite'],
                    ]);
                    $team->id = $index + 1;
                    $team->save();
                }
            }
            $briefs = json_decode(file_get_contents(__DIR__.'/data/briefs.json'), true, 512, JSON_THROW_ON_ERROR);
            $categories = ['Языки', 'Организация обучения', 'Библиотека', 'Обратная связь', 'Навигация'];
            $regions = ['Алматы', 'Астана', 'Шымкент', 'Кызылорда', 'Алматы'];
            foreach ($briefs as $index => $brief) {
                if (Task::find($brief['id'])) {
                    continue;
                }
                $task = new Task([
                    'owner_id' => $index === 4 ? 2 : 1,
                    'status' => 'draft',
                    'scope' => $index % 2 === 0 ? 'kazakhstan' : 'institution',
                ]);
                $task->id = $brief['id'];
                $data = array_merge([
                    'title' => $brief['title'],
                    'organization' => $index === 4 ? 'Демо колледж «Самғау»' : 'Демо университет «Алем»',
                    'region' => $regions[$index], 'category' => $categories[$index],
                    'context' => $brief['description'],
                ], $brief['fields']);
                if ($index === 1) {
                    $data['materials'] = 'Синтетическая таблица свободных часов преподавателей.';
                }
                if ($index === 2) {
                    $data['constraints'] = 'Только синтетические данные; пилот за три недели.';
                }
                $data['confirmedFields'] = ['context'];
                $saved = app(TaskEditor::class)->save($task, $data);
                if ($index > 0) {
                    app(TaskEditor::class)->publish($saved);
                }
            }
            foreach ([2, 2, 3, 4, 5] as $index => $taskId) {
                if (Offer::find($index + 1)) {
                    continue;
                }
                $offer = new Offer([
                    'task_id' => $taskId, 'team_id' => $index + 1,
                    'idea' => 'Соберём небольшой веб-прототип для проверки потребности.',
                    'plan' => 'Уточним сценарий, подготовим интерфейс, реализуем прототип и проведём демонстрацию.',
                    'timeline' => 'Три недели после согласования требований.',
                    'prototype_link' => null, 'decision' => 'pending',
                ]);
                $offer->id = $index + 1;
                $offer->save();
            }
        });
    }
}
