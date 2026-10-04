<?php

declare(strict_types=1);

namespace App\Features\Auth\Support;

use Symfony\Component\HttpFoundation\Cookie;

/** Cookie de sessão (`devfinder_token`, httpOnly) emitido no callback do OAuth e limpo no logout (F4-13). */
final class SessionCookie
{
    /** @param array{name: string, path: string, same_site: 'lax'|'strict'|'none', secure: bool} $config */
    public function __construct(
        private readonly array $config,
        private readonly int $ttlSeconds,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{name: string, path: string, same_site: 'lax'|'strict'|'none', secure: bool} $config */
        $config = config('devfinder.auth.session_cookie');
        $ttl = config('devfinder.auth.jwt_ttl_seconds');

        return new self($config, is_int($ttl) ? $ttl : 0);
    }

    public function name(): string
    {
        return $this->config['name'];
    }

    /** Cookie com o token, com a mesma validade do JWT. */
    public function issue(string $token): Cookie
    {
        return $this->cookie($token, time() + $this->ttlSeconds);
    }

    /** Mesmos atributos do cookie emitido (path, SameSite e Secure precisam bater para o navegador apagá-lo), já expirado. */
    public function forget(): Cookie
    {
        return $this->cookie('', 1);
    }

    private function cookie(string $value, int $expiresAt): Cookie
    {
        return new Cookie($this->config['name'], $value, $expiresAt, $this->config['path'], null, $this->config['secure'], true, false, $this->config['same_site']);
    }
}
