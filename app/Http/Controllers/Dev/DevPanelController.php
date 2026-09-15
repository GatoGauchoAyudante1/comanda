<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\LicenseCharge;
use App\Services\LicenseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Panel del desarrollador: el control del abono mensual del sistema.
 *
 * Vive fuera del sistema del cliente: no usa su guard, no aparece en el menú y
 * se entra escribiendo la URL con la clave del .env.
 * Ver App\Http\Middleware\DevPanelKey.
 */
class DevPanelController extends Controller
{
    public function __construct(private LicenseService $licencia) {}

    public function index(Request $request): View
    {
        if (! $this->claveValida($request)) {
            return view('dev-panel.login');
        }

        return view('dev-panel.index', $this->overview());
    }

    public function login(Request $request): RedirectResponse
    {
        $datos = $request->validate(['key' => ['required', 'string']]);

        $clave = (string) config('license.dev_key');

        if ($clave === '') {
            abort(403, 'El panel de desarrollo no está configurado. Definí DEV_PANEL_KEY en el .env del servidor.');
        }

        if (! hash_equals($clave, $datos['key'])) {
            // Un solo mensaje para cualquier error: no decir si la clave existe
            // ni cuánto se acercó. El POST además va limitado a 10 por minuto.
            return back()->with('error', 'Clave incorrecta.');
        }

        // Se guarda la clave, no un booleano: el middleware la revalida contra
        // el .env en cada request, así cambiarla deja afuera a las sesiones abiertas.
        $request->session()->put('dev_panel_key', $datos['key']);

        return redirect()->route('dev-panel.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('dev_panel_key');

        return redirect()->route('dev-panel.index');
    }

    public function generate(): RedirectResponse
    {
        $cargo = $this->licencia->ensureCurrentCharge();

        if (! $cargo) {
            return redirect()->route('dev-panel.index')
                ->with('error', 'No corresponde generar cargo todavía (abono deshabilitado o antes del día de aviso).');
        }

        return redirect()->route('dev-panel.index')
            ->with('success', "Cargo de {$cargo->period_label} disponible.");
    }

    public function pay(Request $request, LicenseCharge $charge): RedirectResponse
    {
        $datos = $request->validate([
            'method' => ['nullable', 'string', 'max:50'],
            'notes'  => ['nullable', 'string', 'max:255'],
        ]);

        if ($charge->status === 'paid') {
            return redirect()->route('dev-panel.index')->with('error', 'Ese período ya figura como pagado.');
        }

        $this->licencia->markPaid($charge, $datos['method'] ?? null, $datos['notes'] ?? null);

        return redirect()->route('dev-panel.index')
            ->with('success', "Abono de {$charge->period_label} marcado como pagado.");
    }

    public function unpay(LicenseCharge $charge): RedirectResponse
    {
        if ($charge->status !== 'paid') {
            return redirect()->route('dev-panel.index')->with('error', 'Ese período no estaba pagado.');
        }

        $this->licencia->markPending($charge);

        return redirect()->route('dev-panel.index')
            ->with('success', "Abono de {$charge->period_label} vuelto a pendiente.");
    }

    /** Cambia el importe o el vencimiento de un período puntual, sin tocar la configuración. */
    public function updateCharge(Request $request, LicenseCharge $charge): RedirectResponse
    {
        $datos = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $cambios = ['amount' => $datos['amount']];

        // El vencimiento es opcional: mandarlo vacío significa dejarlo como está,
        // no borrarlo. Un cargo sin fecha de vencimiento no se podría vencer.
        if (! empty($datos['due_date'])) {
            $cambios['due_date'] = $datos['due_date'];
        }

        $charge->update($cambios);

        return redirect()->route('dev-panel.index')
            ->with('success', "Período de {$charge->period_label} actualizado.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'enabled'        => ['required', 'boolean'],
            'monthly_amount' => ['required', 'numeric', 'min:0'],
            'notice_day'     => ['required', 'integer', 'between:1,28'],
            'due_day'        => ['required', 'integer', 'between:1,28'],
            'payee'          => ['required', 'string', 'max:80'],
            'payment_alias'  => ['required', 'string', 'max:80'],
            'payment_method' => ['required', 'string', 'max:80'],
            'contact_phone'  => ['nullable', 'string', 'max:40'],
        ]);

        $this->licencia->saveSettings($datos);

        return redirect()->route('dev-panel.index')
            ->with('success', 'Configuración guardada. Aplica a los meses que se generen de acá en adelante.');
    }

    private function claveValida(Request $request): bool
    {
        $clave = (string) config('license.dev_key');

        if ($clave === '') {
            abort(403, 'El panel de desarrollo no está configurado. Definí DEV_PANEL_KEY en el .env del servidor.');
        }

        return hash_equals($clave, (string) $request->session()->get('dev_panel_key'));
    }

    /** Lo que necesita la vista del panel, con el cargo del mes ya asegurado. */
    private function overview(): array
    {
        $this->licencia->ensureCurrentCharge();

        $cargos = LicenseCharge::orderByDesc('period_year')->orderByDesc('period_month')->get();

        $pendientes = $cargos->where('status', 'pending');
        $pagados    = $cargos->where('status', 'paid');

        $pago = $this->licencia->paymentInfo();

        return [
            'settings' => [
                'enabled'        => $this->licencia->isEnabled(),
                'monthly_amount' => $this->licencia->monthlyAmount(),
                'notice_day'     => $this->licencia->noticeDay(),
                'due_day'        => $this->licencia->dueDay(),
                // paymentInfo() habla en corto; el formulario usa el nombre del campo.
                'payee'          => $pago['payee'],
                'payment_alias'  => $pago['alias'],
                'payment_method' => $pago['method'],
                'contact_phone'  => $pago['phone'],
            ],
            'summary' => [
                'total_due'       => (float) $pendientes->sum('amount'),
                'pending_count'   => $pendientes->count(),
                'collected_total' => (float) $pagados->sum('amount'),
                'paid_count'      => $pagados->count(),
            ],
            'charges' => $cargos,
        ];
    }
}
