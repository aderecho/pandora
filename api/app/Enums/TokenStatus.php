<?php

namespace App\Enums;

enum TokenStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
