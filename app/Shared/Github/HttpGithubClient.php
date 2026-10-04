<?php

declare(strict_types=1);

namespace App\Shared\Github;

use App\Shared\Exceptions\GithubUnavailable;
use App\Shared\Http\ResilientHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HttpGithubClient implements GithubClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $redirectUri,
        private readonly int $timeoutSeconds,
    ) {}

    public function authorizeUrl(string $state): string
    {
        $query = ['client_id' => $this->clientId, 'state' => $state];

        if ($this->redirectUri !== null && $this->redirectUri !== '') {
            $query['redirect_uri'] = $this->redirectUri;
        }

        return 'https://github.com/login/oauth/authorize?' . http_build_query($query);
    }

    public function publicProfile(string $login): ?GithubProfile
    {
        try {
            $response = ResilientHttp::send(fn() => $this->request()->get('https://api.github.com/users/' . rawurlencode($login)));
        } catch (ConnectionException $e) {
            throw new GithubUnavailable('github unreachable', $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        // 403 e 429 são o limite da API sem token (60 por hora): é indisponibilidade, não "usuário inexistente".
        if (! $response->ok()) {
            throw new GithubUnavailable("github public profile status {$response->status()}");
        }

        return $this->toProfile($this->json($response));
    }

    public function profileFor(string $code): ?GithubProfile
    {
        try {
            $token = $this->exchange($code);

            return $token === null ? null : $this->profile($token);
        } catch (ConnectionException $e) {
            throw new GithubUnavailable('github unreachable', $e);
        }
    }

    /** @throws ConnectionException */
    private function exchange(string $code): ?string
    {
        $response = ResilientHttp::send(fn() => $this->request()->asJson()->post('https://github.com/login/oauth/access_token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
        ]));

        if ($response->serverError() || ! $response->ok()) {
            throw new GithubUnavailable("github token exchange status {$response->status()}");
        }

        $body = $this->json($response);
        $token = $body['access_token'] ?? null;

        // O GitHub responde 200 mesmo quando recusa o `code` (`bad_verification_code`): é falha do usuário, não do GitHub.
        if (is_string($token) && $token !== '') {
            return $token;
        }

        if (is_string($body['error'] ?? null)) {
            return null;
        }

        throw new GithubUnavailable('github token exchange without token or error');
    }

    /** @throws ConnectionException */
    private function profile(string $token): GithubProfile
    {
        $response = ResilientHttp::send(fn() => $this->request()->withToken($token)->get('https://api.github.com/user'));

        // 401 aqui: o GitHub acabou de emitir o token e já o recusa; trata como indisponibilidade, não como erro do usuário.
        if (! $response->ok()) {
            throw new GithubUnavailable("github profile status {$response->status()}");
        }

        return $this->toProfile($this->json($response));
    }

    /** @param array<string, mixed> $body */
    private function toProfile(array $body): GithubProfile
    {
        $login = $body['login'] ?? null;

        if (! is_string($login) || $login === '') {
            throw new GithubUnavailable('github profile without login');
        }

        return new GithubProfile(
            login: $login,
            name: self::text($body['name'] ?? null, $login),
            bio: self::text($body['bio'] ?? null, ''),
            avatar: self::text($body['avatar_url'] ?? null, ''),
        );
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'devfinder-laravel']);
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    private static function text(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
