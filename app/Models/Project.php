<?php

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\ProjectTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'type',
        'total_price',
        'currency',
        'status',
    ];

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'type' => ProjectTypeEnum::class,
            'status' => ProjectStatusEnum::class,
            'total_price' => 'decimal:2',
        ];
    }

    public function getPaidAmountAttribute()
    {
        // Solo abonos al proyecto: las renovaciones de servicios no reducen su saldo.
        return $this->payments()
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->where('payment_type', '!=', PaymentTypeEnum::RENEWAL->value)
            ->sum('amount');
    }

    public function getBalanceAttribute()
    {
        $balance = $this->total_price - $this->paid_amount;

        return $balance > 0 ? $balance : 0; // Evita mostrar saldos negativos
    }
}
