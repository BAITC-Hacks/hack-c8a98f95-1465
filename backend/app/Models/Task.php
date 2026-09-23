<?php

namespace App\Models;

use App\Services\TaskScorer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'confirmed_fields' => 'array', 'score' => 'integer', 'owner_id' => 'integer',
            'confirmed_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            $task->score = app(TaskScorer::class)->evaluate($task)['score'];
        });
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
