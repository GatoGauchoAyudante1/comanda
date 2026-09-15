<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * Genera el cargo del mes del abono del sistema y muestra cómo viene la deuda.
 *
 * Corre todos los días a las 08:00. No es imprescindible: el cargo también se
 * crea solo al abrir /dev-panel o al cargar el panel del dueño (el banner
 * consulta el mismo servicio). El comando está para que el aviso aparezca
 * temprano aunque nadie entre al sistema ese día, y para poder revisar el
 * estado desde la consola del servidor sin abrir el navegador.
 *
 * El aviso al cliente no se «crea»: el banner del panel se arma en vivo con
 * LicenseService::status(), así que aparece y desaparece con la deuda. Esta app
 * no tiene notificaciones internas, sólo la bitácora de operación del local, y
 * meter ahí una deuda mía sería ensuciarle la auditoría al cliente.
 */
class SendLicenseReminder extends Command
{
    protected $signature = 'licencia:aviso';

    protected $description = 'Genera el cargo del abono del mes y reporta la deuda pendiente';

    public function handle(LicenseService $licencia): int
    {
        if (! $licencia->isEnabled()) {
            $this->info('El abono está deshabilitado. No se genera nada.');

            return self::SUCCESS;
        }

        $cargo = $licencia->ensureCurrentCharge();

        if (! $cargo) {
            $this->line("Todavía no toca avisar: el aviso sale el día {$licencia->noticeDay()} de cada mes.");

            return self::SUCCESS;
        }

        $estado = $cargo->status === 'paid' ? 'pagado' : ($cargo->is_overdue ? 'VENCIDO' : 'pendiente');

        $this->info("Período {$cargo->period_label}: {$estado} · vence el {$cargo->due_date->format('d/m/Y')}.");

        $pendientes = $licencia->pendingCharges();

        if ($pendientes->isEmpty()) {
            $this->line('Sin deuda: el dueño no ve ningún aviso.');

            return self::SUCCESS;
        }

        $total = number_format((float) $pendientes->sum('amount'), 0, ',', '.');

        $this->warn("Aviso activo para el dueño: {$pendientes->count()} período(s) por \${$total}.");

        foreach ($pendientes as $pendiente) {
            $this->line("  · {$pendiente->period_label} · vence {$pendiente->due_date->format('d/m/Y')}"
                . ($pendiente->is_overdue ? ' · vencido' : ''));
        }

        return self::SUCCESS;
    }
}
