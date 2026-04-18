<?php

namespace App\Enums;

enum ServiceTypeEnum: string
{
    case DOMAIN = 'domain';
    case SHARED_HOSTING = 'shared_hosting';
    case VPS = 'vps';
    case MAINTENANCE = 'maintenance';
    case UPDATES = 'updates';
    Case BACKUP = 'backup';
    case OTHER = 'other';
}