<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'type',
        'provider',
        'name',
        'cost_mxn',
        'price_mxn',
        'expiration_date',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    // Relaciones
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    protected function casts(): array
    {
        return [
            'type' => \App\Enums\ServiceTypeEnum::class,
            'status' => \App\Enums\ServiceStatusEnum::class,
            'expiration_date' => 'date',
            'cost_mxn' => 'decimal:2',
            'price_mxn' => 'decimal:2',
        ];
    }
}