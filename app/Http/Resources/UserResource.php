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
            // Si el Enum está casteado, sacamos su valor literal
            'role' => $this->role->value ?? $this->role, 
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}