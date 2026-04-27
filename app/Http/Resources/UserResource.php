<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organization_name' => $this->organization_name,
            'organization_type' => $this->organization_type,
            'role' => $this->role,
            'is_admin' => $this->role === 'admin',
        ];
    }
}
