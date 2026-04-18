<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\FirebaseChannel;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    protected $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return [FirebaseChannel::class];
    }

    public function toFirebase($notifiable)
    {
        return [
            'title' => '¡Pago Recibido! 💰',
            'body' => "Se ha registrado un abono de $" . number_format($this->payment->amount, 2) . " MXN.",
            'extra_data' => [
                'payment_id' => (string) $this->payment->id,
                'click_action' => 'OPEN_PAYMENTS_SCREEN', // Para la app en Flutter
            ]
        ];
    }
}