<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->client->name ?? 'Desconocido',
            'project_id' => $this->project_id,
            'project_name' => $this->project->name ?? null,
            'service_id' => $this->service_id,
            'service_name' => $this->service->name ?? null,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method->value,
            'payment_type' => $this->payment_type->value,
            'status' => $this->status->value,
            'stripe_id' => $this->stripe_payment_intent_id,
            'paid_at' => $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null,
        ];
    }
}