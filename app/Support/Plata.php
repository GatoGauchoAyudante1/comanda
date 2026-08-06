<?php

namespace App\Support;

/**
 * Los importes viven en centavos en toda la aplicación (docs/06-reglas-negocio.md · R-31).
 * Acá está el único lugar donde se convierten para mostrar o para guardar.
 */
class Plata
{
    /** 1250000 -> "$12.500" */
    public static function format(?int $centavos, bool $conSigno = false): string
    {
        $centavos ??= 0;
        $signo = $conSigno && $centavos > 0 ? '+' : ($centavos < 0 ? '-' : '');

        return $signo . '$' . number_format(abs($centavos) / 100, 0, ',', '.');
    }

    /** "12500,50" o 12500.5 -> 1250050 */
    public static function aCentavos(int|float|string $pesos): int
    {
        if (is_string($pesos)) {
            $pesos = (float) str_replace(',', '.', str_replace('.', '', $pesos));
        }

        return (int) round($pesos * 100);
    }
}
