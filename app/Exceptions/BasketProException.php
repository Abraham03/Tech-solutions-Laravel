<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * BasketPro no hizo lo que se le pidio: lo rechazo, no respondio o no esta configurado.
 * `errorCode` es el `code` estable que devuelve BasketPro cuando lo hay.
 */
class BasketProException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }
}
