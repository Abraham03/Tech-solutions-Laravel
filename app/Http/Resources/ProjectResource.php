<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->client->name ?? 'Desconocido', // Relación
            'name' => $this->name,
            'type' => $this->type->value,
            'total_price' => (float) $this->total_price,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}