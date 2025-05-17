<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'weight' => (float) $this->weight,
            'remaining_weight' => (float) $this->remaining_weight,
            'price' => (int) $this->price,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
        ];
    }
}
