<?php

declare(strict_types=1);

namespace App\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `page` nunca reprova: valor inválido vira 1 (paridade com o v1, F3-3). */
class PageRequest extends FormRequest
{
    /** @return array<string, never> */
    public function rules(): array
    {
        return [];
    }

    public function pageNumber(): int
    {
        $raw = $this->query('page');

        return is_string($raw) && ctype_digit($raw) ? max(1, (int) $raw) : 1;
    }
}
