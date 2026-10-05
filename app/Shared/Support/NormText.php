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

    /**
     * Chave de ordenação estável entre ambientes (Fase 7, achada pelo G3 com dados reais): sem caixa e sem acento, na collation "C". A collation
     * padrão do banco muda entre o Neon e o Docker, e o v1 (MySQL `_ai_ci`) ordenava sem caixa: `arduino` vinha antes de `FPGA`, o Postgres em "C"
     * puro põe `FPGA` primeiro.
     *
     * @param literal-string $column
     * @return literal-string
     */
    public static function sortKey(string $column): string
    {
        return "norm_text({$column}) collate \"C\"";
    }

    /**
     * Desempate estável entre ambientes para a mesma chave de `sortKey()`: a própria coluna na collation "C" (`Mobile` antes de `mobile`).
     *
     * @param literal-string $column
     * @return literal-string
     */
    public static function tieBreak(string $column): string
    {
        return "{$column} collate \"C\"";
    }

    /** `%termo%` com `%`, `_` e `\` literais. */
    public static function containsPattern(string $term): string
    {
        return '%' . addcslashes($term, '\\%_') . '%';
    }
}
