#!/bin/sh
# Prova de que o teste de arquitetura protege: injeta um controller que viola as regras, espera FALHA e remove.
# Uso (na raiz): specs/tools/prova-arch.sh   (precisa do ./run.sh e do container de pé)
set -u
F=app/Features/Health/Http/Controllers/ViolaRegraController.php
cat > "$F" <<'PHP'
<?php

declare(strict_types=1);

namespace App\Features\Health\Http\Controllers;

use Illuminate\Support\Facades\DB;

final class ViolaRegraController
{
    public function __invoke(): int
    {
        return DB::table('devs')->count();
    }
}
PHP
./run.sh vendor/bin/pest tests/Arch >/tmp/prova-arch.out 2>&1
CODE=$?
rm -f "$F"
grep -E "⨯|FAILED|Expecting|not to use" /tmp/prova-arch.out | head -8
if [ "$CODE" -eq 0 ]; then echo "PROVA FALHOU: o teste de arquitetura aceitou a violação"; exit 1; fi
echo "PROVA OK: o teste reprovou a violação (exit=$CODE)"
