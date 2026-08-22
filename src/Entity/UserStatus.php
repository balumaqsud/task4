<?php

declare(strict_types=1);

namespace App\Entity;

enum UserStatus: string
{
    case UNVERIFIED = 'UNVERIFIED';
    case ACTIVE = 'ACTIVE';
    case BLOCKED = 'BLOCKED';
}
