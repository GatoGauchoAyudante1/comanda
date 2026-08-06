<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La ocupación de una mesa. Acá vive toda la lógica del cobro por tiempo.
 *
 * Reglas implementadas (docs/06-reglas-negocio.md):
 *   R-01  el tiempo se calcula desde `started_at` en el servidor, nunca en el navegador
 *   R-02  fracción redondeando hacia arriba
 *   R-03  la tarifa está congelada en la sesión
 *   R-04  `started_at` puede haberse ajustado al abrir
 *   R-05  los minutos pausados no se cobran
 */
class TableSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at'            => 'datetime',
            'ended_at'              => 'datetime',
            'paused_at'             => 'datetime',
            'rate_price_per_hour'   => 'integer',
            'rate_rounding_minutes' => 'integer',
            'paused_minutes'        => 'integer',
            'guests'                => 'integer',
        ];
    }

    public function table(): BelongsTo   { return $this->belongsTo(Table::class); }
    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function abierta(): bool { return $this->ended_at === null; }
    public function pausada(): bool { return $this->paused_at !== null; }

    /** Hasta qué momento se cuenta: el cierre, la pausa vigente, o ahora. */
    private function corte(): CarbonInterface
    {
        return $this->ended_at ?? $this->paused_at ?? Carbon::now();
    }

    /** Minutos realmente jugados, descontando pausas. Nunca negativo. */
    public function minutosJugados(): int
    {
        $brutos = $this->started_at->diffInMinutes($this->corte());

        return (int) max(0, $brutos - $this->paused_minutes);
    }

    /**
     * Minutos que se cobran: se redondea hacia arriba a la fracción configurada.
     * Con fracción de 30: 1:25 -> 1:30, 1:31 -> 2:00.
     */
    public function minutosCobrados(): int
    {
        $fraccion = max(1, $this->rate_rounding_minutes);
        $jugados  = $this->minutosJugados();

        if ($jugados === 0) {
            return 0;
        }

        return (int) (ceil($jugados / $fraccion) * $fraccion);
    }

    /** Importe del tiempo, en centavos. */
    public function importeTiempo(): int
    {
        return (int) round($this->minutosCobrados() / 60 * $this->rate_price_per_hour);
    }

    /** "1:25" para mostrar en pantalla. */
    public function tiempoLegible(): string
    {
        $m = $this->minutosJugados();

        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }

    public function pausar(): void
    {
        if ($this->abierta() && ! $this->pausada()) {
            $this->update(['paused_at' => Carbon::now()]);
        }
    }

    public function reanudar(): void
    {
        if ($this->pausada()) {
            $this->update([
                'paused_minutes' => $this->paused_minutes + (int) $this->paused_at->diffInMinutes(Carbon::now()),
                'paused_at'      => null,
            ]);
        }
    }
}
