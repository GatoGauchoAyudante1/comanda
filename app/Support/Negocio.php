<?php

namespace App\Support;

use App\Models\Setting;

/**
 * La configuración del negocio, con dos niveles y una precedencia clara.
 *
 *   1. Lo que el dueño guarda desde la pantalla de Configuración (tabla settings)
 *   2. Si ahí no hay nada, lo que dice el .env (config/negocio.php)
 *
 * El .env define cómo nace la instalación; la pantalla permite cambiarla sin
 * entrar por SSH. "Volver al valor del .env" borra la fila y el .env manda otra vez.
 *
 * Ver docs/02-decisiones.md · D-02 y D-04.
 */
class Negocio
{
    /** Memo por request: la tabla es chica y así no se consulta N veces. */
    private static ?array $memo = null;

    private static function settings(): array
    {
        if (self::$memo === null) {
            self::$memo = Setting::pluck('value', 'key')->all();
        }

        return self::$memo;
    }

    public static function olvidar(): void
    {
        self::$memo = null;
    }

    public static function nombre(): string
    {
        return self::settings()['business.name'] ?? config('negocio.nombre');
    }

    public static function puntoDeVenta(): string
    {
        return self::settings()['receipt.point_of_sale'] ?? config('negocio.comprobante.punto_venta');
    }

    /** ¿Está activo un módulo? salon | pool | delivery | stock */
    public static function modulo(string $nombre): bool
    {
        $guardado = self::settings()["modules.{$nombre}"] ?? null;

        if ($guardado !== null) {
            return filter_var($guardado, FILTER_VALIDATE_BOOL);
        }

        return (bool) config("negocio.modulos.{$nombre}", false);
    }

    /** Si un módulo fue tocado desde la pantalla o sigue viniendo del .env. */
    public static function moduloEsPersonalizado(string $nombre): bool
    {
        return array_key_exists("modules.{$nombre}", self::settings());
    }

    public static function moduloSegunEnv(string $nombre): bool
    {
        return (bool) config("negocio.modulos.{$nombre}", false);
    }

    /** @return array<string, bool> */
    public static function modulos(): array
    {
        $nombres = ['salon', 'pool', 'delivery', 'stock'];

        return array_combine($nombres, array_map([self::class, 'modulo'], $nombres));
    }
}
