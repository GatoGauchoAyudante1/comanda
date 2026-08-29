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

    /**
     * ¿La carta se publica en internet, sin contraseña?
     *
     * Arranca apagada a propósito: publicar precios es una decisión del dueño,
     * no algo que pase por defecto al instalar. Ver App\Http\Controllers\CartaPublicaController.
     */
    public static function cartaPublica(): bool
    {
        return filter_var(self::settings()['menu.public'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /** Bajada opcional de la carta pública: horarios, teléfono, lo que quiera. */
    public static function cartaMensaje(): string
    {
        return trim((string) (self::settings()['menu.note'] ?? ''));
    }

    /**
     * ¿El cajero puede reescribir el detalle del ticket antes de imprimirlo?
     *
     * Apagado de fábrica: el ticket sale con el mismo detalle que la comanda,
     * que es lo que quiere casi todo el mundo. Se prende sólo donde hace falta,
     * porque cada interruptor de más es una pregunta de más para el cajero.
     * Ver docs/06-reglas-negocio.md · R-40.
     */
    public static function detalleTicketEditable(): bool
    {
        return filter_var(self::settings()['receipt.editable_detail'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /** Lo que se ofrece con un toque; el cajero igual puede escribir otra cosa. */
    public const DETALLES_TICKET = ['Consumos mesa', 'Almuerzo', 'Cena', 'Bebidas', 'Varios'];

    /**
     * Textos frecuentes para el detalle del ticket.
     *
     * Si el dueño guardó su propia lista manda la suya, aunque la haya dejado
     * vacía: vaciarla es decir «no me sugieras nada, lo escribo yo».
     *
     * @return array<int, string>
     */
    public static function detallesTicket(): array
    {
        if (! array_key_exists('receipt.detail_templates', self::settings())) {
            return self::DETALLES_TICKET;
        }

        $lista = json_decode(self::settings()['receipt.detail_templates'], true);

        return is_array($lista) ? array_values($lista) : self::DETALLES_TICKET;
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

    /**
     * Roles que pueden marcar una comanda como lista.
     *
     * Por defecto SÓLO cocina. Marcar listo de más hace que un plato salga
     * del tablero sin estar hecho, y el pedido se despacha vacío.
     *
     * `cocina` no se puede sacar de la lista: sin eso nadie podría trabajar.
     *
     * @return array<int, string>
     */
    public static function rolesQueMarcanListo(): array
    {
        $guardado = self::settings()['kitchen.ready_roles'] ?? null;
        $extra    = $guardado ? json_decode($guardado, true) : [];

        if (! is_array($extra)) {
            $extra = [];
        }

        return array_values(array_unique(['cocina', ...$extra]));
    }

    /** El dueño siempre puede: es quien configura esto. */
    public static function puedeMarcarListo(?\App\Models\User $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        return $usuario->role === 'dueno'
            || in_array($usuario->role, self::rolesQueMarcanListo(), true);
    }

    /** @return array<string, bool> */
    public static function modulos(): array
    {
        $nombres = ['salon', 'pool', 'delivery', 'stock'];

        return array_combine($nombres, array_map([self::class, 'modulo'], $nombres));
    }
}
