<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'project_id',
        'service_id',
        'amount',
        'payment_method',
        'payment_type',
        'stripe_payment_intent_id',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    protected function casts(): array
    {
        return [
            'payment_method' => \App\Enums\PaymentMethodEnum::class,
            'payment_type' => \App\Enums\PaymentTypeEnum::class,
            'status' => \App\Enums\PaymentStatusEnum::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}