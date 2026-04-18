<?php

namespace App\Enums;

enum ProjectTypeEnum: string
{
    case WEBSITE = 'website';
    case FRONTEND = 'frontend';
    case WEB_APPLICATION = 'web_application';
    case DESKTOP = 'desktop';
    case BACKEND = 'backend';
    case FLUTTER_APP = 'flutter_app';
    case FULLSTACK = 'fullstack';
    case OTHER = 'other';
}