<?php

declare(strict_types=1);

namespace App\Features\Dev\Actions;

use App\Features\Dev\Queries\DevWriter;
use App\Shared\Github\GithubProfile;

/** Find-or-create do dev a partir do perfil do GitHub (login, `userGithub` do canal). Devolve o username gravado. */
final class EnsureDev
{
    public function __construct(private readonly DevWriter $devs) {}

    public function __invoke(GithubProfile $profile): string
    {
        $this->devs->insertIfMissing($profile);

        return $this->devs->usernameOf(strtolower($profile->login)) ?? strtolower($profile->login);
    }
}
