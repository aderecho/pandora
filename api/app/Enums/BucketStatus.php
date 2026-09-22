<?php

namespace App\Enums;

enum BucketStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
