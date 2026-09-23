<?php

namespace App\Http\Resources;

use App\Services\TaskEditor;
use App\Services\TaskScorer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = [];
        foreach (TaskEditor::FIELDS as $field => $column) {
            $fields[$field] = (string) $this->{$column};
        }

        return array_merge([
            'id' => $this->id, 'ownerId' => $this->owner_id,
        ], $fields, app(TaskScorer::class)->evaluate($this->resource), [
            'confirmedFields' => $this->confirmed_fields,
            'status' => $this->status,
            'confirmedAt' => $this->confirmed_at?->toISOString(),
            'publishedAt' => $this->published_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'offersCount' => $this->whenCounted('offers'),
        ]);
    }
}
