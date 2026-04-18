<?php

namespace App\Enums;

enum ServiceStatusEnum: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}