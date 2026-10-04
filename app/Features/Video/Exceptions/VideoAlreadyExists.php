<?php

declare(strict_types=1);

namespace App\Features\Video\Exceptions;

use App\Features\Video\Data\VideoView;
use App\Shared\Exceptions\ApiException;
use Throwable;

/** Vídeo já existe: 409 com o `errorMessage` e o vídeo existente por inteiro (formato do contrato, `erros-v1.md`). */
final class VideoAlreadyExists extends ApiException
{
    public function __construct(
        private readonly string $title,
        private readonly VideoView $existing,
        ?Throwable $previous = null,
    ) {
        parent::__construct("video({$title}) already exists", $previous);
    }

    public function status(): int
    {
        return 409;
    }

    public function body(): array
    {
        return ['errorMessage' => $this->getMessage()] + $this->existing->toContract();
    }
}
