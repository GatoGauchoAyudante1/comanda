<?php

namespace App\Support;

/**
 * Conversión entre lo que escribe el usuario y la unidad base del insumo.
 *
 * El stock SIEMPRE se guarda en unidad base (g, ml, un). Pero nadie compra
 * "8000 g de queso": compra una horma de 8 kg. Acá se traduce.
 *
 * Ver docs/05-modulo-stock.md.
 */
class Unidades
{
    /** Lo que se le ofrece al usuario según la unidad base del insumo. */
    public const EQUIVALENCIAS = [
        'g'  => ['g' => 1, 'kg' => 1000],
        'ml' => ['ml' => 1, 'L' => 1000],
        'un' => ['un' => 1],
    ];

    /** Cuántas unidades base equivalen a lo escrito. Ej: 8 kg -> 8000 g */
    public static function aBase(float $cantidad, string $unidad, string $unidadBase): float
    {
        $factor = self::EQUIVALENCIAS[$unidadBase][$unidad] ?? 1;

        return $cantidad * $factor;
    }

    /** El camino inverso, para mostrar. Ej: 14200 g -> "14,2 kg" */
    public static function legible(float $enBase, string $unidadBase, int $decimales = 2): string
    {
        $opciones = self::EQUIVALENCIAS[$unidadBase] ?? ['un' => 1];

        // Se elige la unidad más grande que deje un número legible.
        foreach (array_reverse($opciones, true) as $etiqueta => $factor) {
            if (abs($enBase) >= $factor || $factor === 1) {
                $valor = $enBase / $factor;

                return rtrim(rtrim(number_format($valor, $decimales, ',', '.'), '0'), ',') . ' ' . $etiqueta;
            }
        }

        return number_format($enBase, $decimales, ',', '.') . ' ' . $unidadBase;
    }

    /** Las unidades que se pueden elegir para un insumo. */
    public static function opciones(string $unidadBase): array
    {
        return array_keys(self::EQUIVALENCIAS[$unidadBase] ?? ['un' => 1]);
    }

    /**
     * La unidad en la que se compra, no en la que se guarda.
     * El queso se guarda en gramos pero se compra por kilo.
     *
     * @return array{unidad:string, factor:int}
     */
    public static function comercial(string $unidadBase): array
    {
        $opciones = self::EQUIVALENCIAS[$unidadBase] ?? ['un' => 1];
        $unidad   = array_key_last($opciones);

        return ['unidad' => $unidad, 'factor' => $opciones[$unidad]];
    }
}
