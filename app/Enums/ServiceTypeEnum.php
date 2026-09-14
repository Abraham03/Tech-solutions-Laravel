<?php

namespace App\Enums;

enum ServiceTypeEnum: string
{
    case DOMAIN = 'domain';
    case SHARED_HOSTING = 'shared_hosting';
    case VPS = 'vps';
    case MAINTENANCE = 'maintenance';
    case UPDATES = 'updates';
    case BACKUP = 'backup';
    // Una cuenta de BasketPro: darlo de alta crea la cuenta allá (ver InfrastructureService).
    case BASKETPRO_SUBSCRIPTION = 'basketpro_subscription';
    case OTHER = 'other';
}
