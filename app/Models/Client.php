<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Client extends Model
{
    use HasFactory, SoftDeletes, Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'contact_name',
        'email',
        'phone_number',
        'stripe_customer_id',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }
}