<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'taskId' => $this->task_id, 'teamId' => $this->team_id,
            'team' => new TeamResource($this->whenLoaded('team')),
            'idea' => $this->idea, 'plan' => $this->plan, 'timeline' => $this->timeline,
            'prototypeLink' => $this->prototype_link, 'decision' => $this->decision,
            'decidedAt' => $this->decided_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
