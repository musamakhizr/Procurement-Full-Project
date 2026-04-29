<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SourcingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'details' => $this->details,
            'quantity' => $this->quantity,
            'budget_text' => $this->budget_text,
            'delivery_date' => $this->delivery_date?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'links' => $this->whenLoaded('links', fn () => $this->links->pluck('url')->values()->all(), []),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'organization_name' => $this->user->organization_name,
            ]),
        ];
    }
}
