<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens,HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        // fcm_token se conserva en la tabla por compatibilidad, pero ya no se
        // escribe: los tokens viven en device_tokens, uno por dispositivo.
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
        ];
    }

    /**
     * Dispositivos donde este usuario recibe notificaciones push.
     * Uno por navegador o aplicacion instalada.
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    // Un usuario (con rol 'client') tiene un perfil en el CRM
    public function clientProfile()
    {
        return $this->hasOne(Client::class);
    }
}
