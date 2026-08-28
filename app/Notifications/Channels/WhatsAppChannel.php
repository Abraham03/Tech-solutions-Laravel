<?php

namespace App\Notifications\Channels;

use App\Notifications\ServiceExpiringNotification;
use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            throw new \Exception('La notificación no tiene el método toWhatsApp.');
        }

        /** @var ServiceExpiringNotification $notification */
        $message = $notification->toWhatsApp($notifiable);

        return $this->whatsappService->sendTemplate(
            $message['to'],
            $message['template'],
            $message['components'] ?? []
        );
    }
}
