<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            'auditable_type' => $this->auditable_type,
            'auditable_id'   => $this->auditable_id,
            
            'user_id' => $this->user_id,
            'user'    => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),

            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
