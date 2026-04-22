<?php

namespace App\Models;

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
            'type' => \App\Enums\ProjectTypeEnum::class,
            'status' => \App\Enums\ProjectStatusEnum::class,
            'total_price' => 'decimal:2',
        ];
    }
    public function getPaidAmountAttribute() {
        return $this->payments()->where('status', 'COMPLETED')->sum('amount');
    }

    public function getBalanceAttribute()
    {
        $balance = $this->total_price - $this->paid_amount;
        return $balance > 0 ? $balance : 0; // Evita mostrar saldos negativos
    }
}