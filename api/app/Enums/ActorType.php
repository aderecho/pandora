<?php

namespace App\Enums;

enum ActorType: string
{
    case User = 'user';
    case Token = 'token';
    case System = 'system';
}
