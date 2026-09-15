<?php

namespace App\Services;

use App\Models\LicenseCharge;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Abono mensual del sistema: genera la deuda del mes y la da por cobrada.
 *
 * Toda la lógica vive acá; el controlador de /dev-panel sólo valida y responde,
 * y el comando programado y el banner del dueño consumen los mismos métodos.
 *
 * La configuración va en la tabla `settings` con el prefijo `license_`. El
 * importe se guarda como texto y no como int porque `Setting` no tiene tipo
 * decimal y el abono puede llevar centavos.
 */
class LicenseService
{
    public function isEnabled(): bool
    {
        return (bool) Setting::get('license_enabled', true);
    }

    public function monthlyAmount(): float
    {
        return (float) Setting::get('license_monthly_amount', 0);
    }

    public function noticeDay(): int
    {
        return $this->clampDay((int) Setting::get('license_notice_day', 1));
    }

    public function dueDay(): int
    {
        return $this->clampDay((int) Setting::get('license_due_day', 10));
    }

    /** Lo que el cliente ve en el banner para pagarme. */
    public function paymentInfo(): array
    {
        return [
            'payee'  => (string) Setting::get('license_payee', ''),
            'alias'  => (string) Setting::get('license_payment_alias', ''),
            'method' => (string) Setting::get('license_payment_method', ''),
            'phone'  => (string) Setting::get('license_contact_phone', ''),
        ];
    }

    /** Guarda la configuración del abono. Las claves son las del formulario del panel. */
    public function saveSettings(array $datos): void
    {
        Setting::put('license_enabled', $datos['enabled'] ? '1' : '0', 'bool');
        Setting::put('license_monthly_amount', (string) $datos['monthly_amount']);
        Setting::put('license_notice_day', (string) $this->clampDay((int) $datos['notice_day']), 'int');
        Setting::put('license_due_day', (string) $this->clampDay((int) $datos['due_day']), 'int');
        Setting::put('license_payee', (string) $datos['payee']);
        Setting::put('license_payment_alias', (string) $datos['payment_alias']);
        Setting::put('license_payment_method', (string) $datos['payment_method']);
        Setting::put('license_contact_phone', (string) ($datos['contact_phone'] ?? ''));
    }

    public function dueDateFor(int $year, int $month): Carbon
    {
        $primero = Carbon::create($year, $month, 1);

        // Febrero no tiene 30: el día 28 del clamp igual puede pasarse en meses
        // más cortos si alguien edita la base a mano, así que se acota de nuevo.
        return $primero->setDay(min($this->dueDay(), $primero->daysInMonth));
    }

    /**
     * El cargo del mes en curso, creándolo si todavía no existe.
     *
     * Es idempotente y se llama de forma perezosa: desde el comando programado,
     * desde cada carga de /dev-panel y desde el estado que alimenta el banner.
     * Así el cargo aparece igual aunque el cron del servidor nunca se haya
     * configurado, que es lo que pasa en la mitad de las instalaciones.
     */
    public function ensureCurrentCharge(?Carbon $today = null): ?LicenseCharge
    {
        $hoy = $today ?? now();

        if (! $this->isEnabled() || $hoy->day < $this->noticeDay()) {
            return null;
        }

        return LicenseCharge::firstOrCreate(
            ['period_year' => $hoy->year, 'period_month' => $hoy->month],
            [
                'amount'   => $this->monthlyAmount(),
                'due_date' => $this->dueDateFor($hoy->year, $hoy->month),
                'status'   => 'pending',
            ],
        );
    }

    /** Pendientes del más viejo al más nuevo: primero se cobra lo que más atrasado está. */
    public function pendingCharges()
    {
        return LicenseCharge::pending()
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();
    }

    public function markPaid(LicenseCharge $charge, ?string $method = null, ?string $notes = null): LicenseCharge
    {
        $charge->update([
            'status'      => 'paid',
            'paid_at'     => now(),
            'paid_method' => $method,
            'paid_notes'  => $notes,
        ]);

        // El aviso del dueño se arma en vivo desde status(), así que al no
        // quedar pendientes el banner desaparece solo: no hay nada que resolver.
        return $charge;
    }

    /** Deshace un pago marcado por error. */
    public function markPending(LicenseCharge $charge): LicenseCharge
    {
        $charge->update([
            'status'      => 'pending',
            'paid_at'     => null,
            'paid_method' => null,
            'paid_notes'  => null,
        ]);

        return $charge;
    }

    /** Lo que consume el banner del dueño y el endpoint de estado. */
    public function status(): array
    {
        $this->ensureCurrentCharge();

        $pendientes = $this->pendingCharges();

        return [
            'enabled'        => $this->isEnabled(),
            'monthly_amount' => $this->monthlyAmount(),
            'notice_day'     => $this->noticeDay(),
            'due_day'        => $this->dueDay(),
            'payment'        => $this->paymentInfo(),
            'has_debt'       => $this->isEnabled() && $pendientes->isNotEmpty(),
            'total_due'      => (float) $pendientes->sum('amount'),
            'pending_count'  => $pendientes->count(),
            'has_overdue'    => $pendientes->contains(fn (LicenseCharge $c) => $c->is_overdue),
            'oldest_pending' => $pendientes->first(),
            'charges'        => $pendientes,
        ];
    }

    /**
     * Ningún mes tiene menos de 28 días: acotando ahí, ni el aviso ni el
     * vencimiento pueden caer en un día que en febrero no existe.
     */
    private function clampDay(int $day): int
    {
        return max(1, min(28, $day));
    }
}
