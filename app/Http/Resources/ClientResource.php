<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'company_name' => $this->name, // Mapeamos 'name' a algo más semántico para el frontend
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'stripe_id' => $this->stripe_customer_id,
            'registered_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}