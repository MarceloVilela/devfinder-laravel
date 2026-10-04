<?php

declare(strict_types=1);

namespace App\Features\Dev\Actions;

use App\Features\Dev\Data\DevView;
use App\Features\Dev\Exceptions\GithubUserNotFound;
use App\Features\Dev\Queries\DevQueries;
use App\Features\Dev\Queries\DevWriter;
use App\Shared\Exceptions\GithubUnavailable;
use App\Shared\Github\GithubClient;

/** `POST /devs`: sempre devolve o dev; só consulta o GitHub se ele ainda não existe (F5-7). */
final class StoreDev
{
    public function __construct(
        private readonly DevWriter $writer,
        private readonly DevQueries $queries,
        private readonly GithubClient $github,
    ) {}

    /**
     * @throws GithubUserNotFound
     * @throws GithubUnavailable
     */
    public function __invoke(string $username): DevView
    {
        $stored = $this->writer->usernameOf($username);

        if ($stored === null) {
            $profile = $this->github->publicProfile($username) ?? throw new GithubUserNotFound("github user {$username} not found");
            $this->writer->insertIfMissing($profile);
            $stored = strtolower($profile->login);
        }

        return $this->queries->byUsername($stored) ?? throw new GithubUserNotFound("dev {$stored} vanished");
    }
}
