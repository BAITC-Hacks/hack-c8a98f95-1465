<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'organization' => $this->organization,
            'interests' => $this->interests, 'skills' => $this->skills, 'technologies' => $this->technologies,
        ];
    }
}
