<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case STRIPE = 'stripe';
}