<?php

namespace App\Enums;

enum ProjectStatusEnum: string
{
    case QUOTED = 'quoted';
    case DEVELOPMENT = 'development';
    case COMPLETED = 'completed';
    case SUSPENDED = 'suspended';
}