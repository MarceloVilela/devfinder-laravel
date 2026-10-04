<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/** Papel do dev (F5-15). O valor é o do ENUM `dev_role` do banco; `ADMIN` só se define direto no banco. */
enum DevRole: string
{
    case User = 'USER';
    case Admin = 'ADMIN';
}
