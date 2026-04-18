<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Services\WhatsAppService;

class WhatsAppChannel
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            throw new \Exception('La notificación no tiene el método toWhatsApp.');
        }

        /** @var \App\Notifications\ServiceExpiringNotification $notification */
        $message = $notification->toWhatsApp($notifiable);

        return $this->whatsappService->sendTemplate(
            $message['to'],
            $message['template'],
            $message['components'] ?? []
        );
    }
}