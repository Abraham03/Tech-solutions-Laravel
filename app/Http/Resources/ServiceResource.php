<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_name' => $this->project->name ?? 'Desconocido', // Relación
            'type' => $this->type->value,
            'provider' => $this->provider, // Ej. Hostinger, GoDaddy
            'name' => $this->name, // Ej. techsolutions.management
            'cost_mxn' => (float) $this->cost_mxn,
            'price_mxn' => (float) $this->price_mxn,
            'expiration_date' => $this->expiration_date->format('Y-m-d'),
            'status' => $this->status->value,
        ];
    }
}