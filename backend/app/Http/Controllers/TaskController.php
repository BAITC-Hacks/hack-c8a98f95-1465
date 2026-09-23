<?php

namespace App\Http\Controllers;

use App\Http\Middleware\DemoIdentity;
use App\Http\Requests\TaskWriteRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskEditor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'scope' => ['sometimes', 'nullable', Rule::in(['institution', 'kazakhstan'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort' => ['sometimes', Rule::in(['score', 'newest'])],
            'preferredOrganization' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $query = Task::query()->where('status', 'published')->withCount('offers');
        foreach (['category', 'region', 'scope'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['preferredOrganization'])) {
            $query->orderByRaw(
                'CASE WHEN scope = ? AND organization = ? THEN 0 ELSE 1 END',
                ['institution', $filters['preferredOrganization']]
            );
        }
        $query->orderByDesc(($filters['sort'] ?? 'score') === 'newest' ? 'published_at' : 'score')->orderByDesc('id');
        $tasks = $query->get();
        if (! empty($filters['q'])) {
            // SQLite LIKE is not Unicode case-insensitive. Keep Cyrillic search correct.
            $needle = $filters['q'];
            $tasks = $tasks->filter(fn (Task $task) => mb_stripos(
                implode(' ', [$task->title, $task->context, $task->organization, $task->category]),
                $needle, 0, 'UTF-8'
            ) !== false)->values();
        }

        return TaskResource::collection($tasks)->additional(['meta' => ['total' => $tasks->count()]]);
    }

    public function mine(Request $request)
    {
        return TaskResource::collection(
            Task::where('owner_id', $request->attributes->get('demoId'))->withCount('offers')->latest('id')->get()
        );
    }

    public function show(Request $request, Task $task): TaskResource
    {
        abort_unless($task->status === 'published' || $task->owner_id === DemoIdentity::customerId($request), 404);

        return new TaskResource($task->loadCount('offers'));
    }

    public function store(TaskWriteRequest $request, TaskEditor $editor)
    {
        $task = $editor->save(new Task([
            'owner_id' => $request->attributes->get('demoId'),
            'status' => 'draft', 'scope' => 'kazakhstan',
        ]), $request->validated());

        return (new TaskResource($task->loadCount('offers')))->response()->setStatusCode(201);
    }

    public function update(TaskWriteRequest $request, Task $task, TaskEditor $editor): TaskResource
    {
        $this->authorizeOwner($request, $task);

        return new TaskResource($editor->save($task, $request->validated())->loadCount('offers'));
    }

    public function publish(Request $request, Task $task, TaskEditor $editor): TaskResource
    {
        $this->authorizeOwner($request, $task);
        $request->validate(['confirmed' => ['required', 'boolean', 'accepted']], [
            'confirmed.required' => 'Подтвердите карточку перед публикацией.',
            'confirmed.accepted' => 'Подтвердите карточку перед публикацией.',
        ]);

        return new TaskResource($editor->publish($task)->loadCount('offers'));
    }

    private function authorizeOwner(Request $request, Task $task): void
    {
        abort_unless($task->owner_id === $request->attributes->get('demoId'), 403);
    }
}
