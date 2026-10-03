<?php

declare(strict_types=1);

namespace App\Shared\Support;

/** Fragmentos de SQL sobre `norm_text()` (sem caixa e sem acento, P-4); o termo sempre vai por parâmetro. */
final class NormText
{
    /**
     * `norm_text(coluna) = norm_text(?)` (usa o índice de expressão).
     *
     * @param literal-string $column
     * @return literal-string
     */
    public static function equals(string $column): string
    {
        return "norm_text({$column}) = norm_text(?)";
    }

    /**
     * `norm_text(coluna) LIKE norm_text(?) ESCAPE '\'` (usa o GIN `pg_trgm`); o padrão vem de `containsPattern()`.
     *
     * @param literal-string $column
     * @return literal-string
     */
    public static function like(string $column): string
    {
        return "norm_text({$column}) LIKE norm_text(?) ESCAPE '\\'";
    }

    /** `%termo%` com `%`, `_` e `\` literais. */
    public static function containsPattern(string $term): string
    {
        return '%' . addcslashes($term, '\\%_') . '%';
    }
}
