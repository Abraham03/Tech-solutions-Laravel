<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;

class ServiceExpiringNotification extends Notification
{
    use Queueable;

    protected $service;
    protected $paymentUrl;

    /**
     * Recibe el objeto del servicio y la URL generada por Stripe.
     */
    public function __construct($service, string $paymentUrl)
    {
        $this->service = $service;
        $this->paymentUrl = $paymentUrl;
    }

    public function via($notifiable)
    {
        // Definimos que el envío es a través de nuestro canal personalizado de WhatsApp
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp($notifiable)
    {
        // Limpiamos la URL de Stripe para mandar solo la parte dinámica al botón
        $dynamicUrlPart = str_replace('https://checkout.stripe.com/', '', $this->paymentUrl);

        return [
            'to' => $notifiable->phone_number, 
            'template' => 'alerta_vencimiento_pago', // El nombre de la plantilla que acabas de crear
            'components' => [
                // Variables del texto (Cuerpo)
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $this->service->name],
                        ['type' => 'text', 'text' => $this->service->expiration_date->format('d-m-Y')],
                    ]
                ],
                // Variable del botón (URL dinámica)
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0', // El índice 0 significa que es el primer botón
                    'parameters' => [
                        ['type' => 'text', 'text' => $dynamicUrlPart]
                    ]
                ]
            ]
        ];
    }
}