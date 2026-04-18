<?php

namespace App\Enums;

enum PaymentTypeEnum: string
{
    case ADVANCE = 'advance'; // Anticipo
    case FINAL = 'final';     // Finiquito
    case RENEWAL = 'renewal'; // Renovación
}