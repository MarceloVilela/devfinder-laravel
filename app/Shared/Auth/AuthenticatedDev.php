<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Dev identificado pelo token da requisição (o middleware `auth` o carrega em 1 query, F4-7). */
final readonly class AuthenticatedDev
{
    public const ATTRIBUTE = 'auth.dev';

    /** Dev que o middleware `auth` deixou na requisição; só chamar em rota com `auth`. */
    public static function of(Request $request): self
    {
        $dev = $request->attributes->get(self::ATTRIBUTE);

        abort_unless($dev instanceof self, 401);

        return $dev;
    }

    public function __construct(
        public string $id,
        public string $username,
        public string $name,
        public string $bio,
        public string $avatar,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public DevRole $role = DevRole::User,
    ) {}

    public function isAdmin(): bool
    {
        return $this->role === DevRole::Admin;
    }
}
