<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Token de Firebase de UN dispositivo concreto.
 *
 * Un usuario puede tener varios: el celular y el escritorio a la vez. Antes esto
 * era una sola columna en users, asi que registrar un aparato desconectaba al
 * otro sin avisar.
 */
class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
