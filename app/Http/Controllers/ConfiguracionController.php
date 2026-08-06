<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TableRate;
use App\Models\User;
use App\Models\Zone;
use App\Support\Negocio;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Ajustes del negocio.
 *
 * Precedencia: lo que se guarda acá pisa al .env; si no hay nada guardado,
 * manda el .env. Ver App\Support\Negocio y docs/02-decisiones.md · D-02.
 */
class ConfiguracionController extends Controller
{
    private const MODULOS = [
        'salon'    => ['Salón', 'Mesas comunes con consumo.'],
        'pool'     => ['Mesas de pool', 'Cobro por tiempo, además del consumo.'],
        'delivery' => ['Delivery y retiro', 'Pedidos, tablero, repartidores y rendición.'],
        'stock'    => ['Stock e insumos', 'Recetas, descuento automático y conteo.'],
    ];

    public function index(): View
    {
        return view('configuracion', [
            'modulos'      => self::MODULOS,
            'tarifas'      => TableRate::orderByDesc('is_default')->orderBy('name')->get(),
            'zonas'        => Zone::orderBy('name')->get(),
            'usuarios'     => User::orderBy('role')->orderBy('name')->get(),
            'roles'        => User::ROLES,
        ]);
    }

    public function guardarNegocio(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'max:60'],
            'punto_venta'  => ['required', 'string', 'max:8'],
        ]);

        Setting::put('business.name', $datos['nombre']);
        Setting::put('receipt.point_of_sale', $datos['punto_venta']);
        Negocio::olvidar();

        return back()->with('ok', 'Datos del negocio actualizados.');
    }

    public function alternarModulo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'modulo' => ['required', Rule::in(array_keys(self::MODULOS))],
        ]);

        $nuevo = ! Negocio::modulo($datos['modulo']);

        Setting::put("modules.{$datos['modulo']}", $nuevo ? '1' : '0', 'bool');
        Negocio::olvidar();

        $nombre = self::MODULOS[$datos['modulo']][0];

        return back()->with('ok', "«{$nombre}» " . ($nuevo ? 'activado' : 'desactivado') . '.');
    }

    /** Borra el valor guardado y deja que vuelva a mandar el .env. */
    public function restablecerModulo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'modulo' => ['required', Rule::in(array_keys(self::MODULOS))],
        ]);

        Setting::where('key', "modules.{$datos['modulo']}")->delete();
        Negocio::olvidar();

        $valor = Negocio::moduloSegunEnv($datos['modulo']) ? 'activado' : 'desactivado';

        return back()->with('ok', "Vuelve a mandar el .env: queda {$valor}.");
    }

    public function guardarTarifa(Request $request, ?TableRate $tarifa = null): RedirectResponse
    {
        $datos = $request->validate([
            'name'             => ['required', 'string', 'max:60'],
            'price_per_hour'   => ['required', 'numeric', 'min:0'],
            'rounding_minutes' => ['required', 'integer', 'in:1,10,15,30,60'],
            'is_default'       => ['nullable', 'boolean'],
        ]);

        $valores = [
            'name'             => $datos['name'],
            'price_per_hour'   => Plata::aCentavos($datos['price_per_hour']),
            'rounding_minutes' => (int) $datos['rounding_minutes'],
            'is_default'       => $request->boolean('is_default'),
            'active'           => true,
        ];

        if ($request->boolean('is_default')) {
            TableRate::query()->update(['is_default' => false]);
        }

        $tarifa?->exists ? $tarifa->update($valores) : TableRate::create($valores);

        return back()->with('ok', 'Tarifa guardada. Las mesas abiertas mantienen la suya (R-03).');
    }

    public function guardarZona(Request $request, ?Zone $zona = null): RedirectResponse
    {
        $datos = $request->validate([
            'name'         => ['required', 'string', 'max:60'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
        ]);

        $valores = [
            'name'         => $datos['name'],
            'delivery_fee' => Plata::aCentavos($datos['delivery_fee']),
            'active'       => true,
        ];

        $zona?->exists ? $zona->update($valores) : Zone::create($valores);

        return back()->with('ok', 'Zona guardada. Los pedidos ya tomados no cambian (R-15).');
    }

    public function guardarUsuario(Request $request, ?User $usuario = null): RedirectResponse
    {
        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:60'],
            'email'    => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($usuario)],
            'role'     => ['required', Rule::in(User::ROLES)],
            'password' => [$usuario?->exists ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        $valores = [
            'name'  => $datos['name'],
            'email' => $datos['email'],
            'role'  => $datos['role'],
        ];

        if (! empty($datos['password'])) {
            $valores['password'] = Hash::make($datos['password']);
        }

        if ($usuario?->exists) {
            $usuario->update($valores);
        } else {
            User::create([...$valores, 'active' => true]);
        }

        return back()->with('ok', 'Usuario guardado.');
    }

    public function alternarUsuario(Request $request, User $usuario): RedirectResponse
    {
        // Nadie se deja a sí mismo afuera.
        if ($usuario->id === $request->user()->id) {
            return back()->with('error', 'No podés desactivar tu propio usuario.');
        }

        $usuario->update(['active' => ! $usuario->active]);

        return back()->with('ok', "«{$usuario->name}» " . ($usuario->active ? 'activado' : 'desactivado') . '.');
    }
}
