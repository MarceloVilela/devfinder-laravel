<?php

declare(strict_types=1);

namespace App\Shared\Http\Requests;

use App\Shared\Auth\AuthenticatedDev;
use Illuminate\Foundation\Http\FormRequest;

/** `page` nunca reprova: valor inválido vira 1 (paridade com o v1, F3-3). */
class PageRequest extends FormRequest
{
    /** @return array<string, never> */
    public function rules(): array
    {
        return [];
    }

    /** Dev do token, se a rota tem o middleware `auth` ou `auth.optional` e o token valeu. */
    public function actor(): ?AuthenticatedDev
    {
        $dev = $this->attributes->get(AuthenticatedDev::ATTRIBUTE);

        return $dev instanceof AuthenticatedDev ? $dev : null;
    }

    public function pageNumber(): int
    {
        $raw = $this->query('page');

        return is_string($raw) && ctype_digit($raw) ? max(1, (int) $raw) : 1;
    }
}
