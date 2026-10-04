<?php

declare(strict_types=1);

namespace App\Features\Auth\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/** JWT HS256 com payload `{username, iat, exp}` (F4-4). O algoritmo é fixo: um token `alg: none` ou RS256 nunca passa. */
final class TokenCodec
{
    public const MIN_SECRET_BYTES = 32;

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
    ) {}

    public function issue(string $username): string
    {
        $this->assertSecret();
        $now = time();

        return JWT::encode(['username' => $username, 'iat' => $now, 'exp' => $now + $this->ttlSeconds], $this->secret, 'HS256');
    }

    /** Username do token, ou `null` se estiver malformado, adulterado, expirado ou sem `exp`/`username`. Sem distinguir o motivo. */
    public function username(string $token): ?string
    {
        $this->assertSecret();

        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable) {
            return null;
        }

        $username = $claims->username ?? null;

        return isset($claims->exp) && is_string($username) && $username !== '' ? $username : null;
    }

    private function assertSecret(): void
    {
        if (strlen($this->secret) < self::MIN_SECRET_BYTES) {
            throw new RuntimeException('JWT_SECRET ausente ou com menos de ' . self::MIN_SECRET_BYTES . ' bytes');
        }
    }
}
