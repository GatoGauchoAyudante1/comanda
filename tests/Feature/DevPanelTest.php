<?php

namespace Tests\Feature;

use App\Models\LicenseCharge;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel del desarrollador y abono mensual del sistema.
 *
 * Lo que se cuida acá es la puerta: la clave vive en el .env y ningún usuario
 * de la app —ni el dueño— entra sin ella. Y que el cargo del mes se cree una
 * sola vez por más veces que se entre al panel.
 */
class DevPanelTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'clave-larga-de-prueba-para-el-panel';

    protected function setUp(): void
    {
        parent::setUp();

        config(['license.dev_key' => self::CLAVE]);

        Setting::put('license_enabled', '1', 'bool');
        Setting::put('license_monthly_amount', '50000');
        Setting::put('license_notice_day', '1', 'int');
        Setting::put('license_due_day', '10', 'int');
    }

    public function test_sin_clave_en_el_env_el_panel_no_existe(): void
    {
        config(['license.dev_key' => '']);

        $this->get('/dev-panel')->assertForbidden();
        $this->post('/dev-panel/login', ['key' => 'lo-que-sea'])->assertForbidden();
    }

    public function test_la_clave_incorrecta_no_deja_entrar(): void
    {
        $this->post('/dev-panel/login', ['key' => 'incorrecta'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Clave incorrecta.')
            ->assertSessionMissing('dev_panel_key');

        $this->get('/dev-panel')->assertOk()->assertSee('Acceso restringido');
    }

    public function test_con_la_clave_correcta_se_entra_y_la_sesion_persiste(): void
    {
        $this->post('/dev-panel/login', ['key' => self::CLAVE])
            ->assertRedirect(route('dev-panel.index'));

        $this->get('/dev-panel')->assertOk()->assertSee('Panel del desarrollador');

        $this->post('/dev-panel/logout')->assertRedirect(route('dev-panel.index'));

        $this->get('/dev-panel')->assertOk()->assertSee('Acceso restringido');
    }

    /** Es la razón de revalidar la sesión contra el .env en cada request. */
    public function test_cambiar_la_clave_del_env_invalida_las_sesiones_abiertas(): void
    {
        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->get('/dev-panel')->assertSee('Panel del desarrollador');

        config(['license.dev_key' => 'otra-clave-distinta']);

        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->get('/dev-panel')->assertSee('Acceso restringido');

        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->post('/dev-panel/generate')->assertRedirect(route('dev-panel.index'));
    }

    /** El panel es del desarrollador: el guard de la app no abre esa puerta. */
    public function test_el_dueno_logueado_tampoco_entra_sin_la_clave(): void
    {
        $dueno = User::create([
            'name' => 'Dueño', 'email' => 'dueno@test.local',
            'password' => 'secreto', 'role' => 'dueno', 'active' => true,
        ]);

        $this->actingAs($dueno)->get('/dev-panel')->assertSee('Acceso restringido');
        $this->actingAs($dueno)->post('/dev-panel/generate')->assertRedirect(route('dev-panel.index'));
    }

    public function test_entrar_diez_veces_crea_un_solo_cargo(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withSession(['dev_panel_key' => self::CLAVE])->get('/dev-panel')->assertOk();
        }

        $this->assertSame(1, LicenseCharge::count());
    }

    public function test_antes_del_dia_de_aviso_no_se_genera_nada(): void
    {
        Setting::put('license_notice_day', '15', 'int');

        $servicio = app(LicenseService::class);

        $this->assertNull($servicio->ensureCurrentCharge(Carbon::create(2026, 9, 14)));
        $this->assertNotNull($servicio->ensureCurrentCharge(Carbon::create(2026, 9, 15)));
    }

    public function test_marcar_pagado_deshacerlo_y_editar_el_importe(): void
    {
        $cargo = app(LicenseService::class)->ensureCurrentCharge();

        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->post("/dev-panel/charges/{$cargo->id}/pay", ['method' => 'Transferencia'])
            ->assertRedirect(route('dev-panel.index'));

        $this->assertSame('paid', $cargo->fresh()->status);

        // Dos veces no: el segundo pago tiene que rebotar sin tocar nada.
        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->post("/dev-panel/charges/{$cargo->id}/pay")
            ->assertSessionHas('error', 'Ese período ya figura como pagado.');

        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->post("/dev-panel/charges/{$cargo->id}/unpay");

        $this->assertSame('pending', $cargo->fresh()->status);
        $this->assertNull($cargo->fresh()->paid_at);

        $this->withSession(['dev_panel_key' => self::CLAVE])
            ->put("/dev-panel/charges/{$cargo->id}", ['amount' => 77000]);

        $this->assertSame('77000.00', $cargo->fresh()->amount);
    }

    public function test_el_estado_del_abono_es_del_dueno_y_no_del_staff(): void
    {
        $dueno = User::create([
            'name' => 'Dueño', 'email' => 'dueno@test.local',
            'password' => 'secreto', 'role' => 'dueno', 'active' => true,
        ]);
        $cajero = User::create([
            'name' => 'Cajero', 'email' => 'cajero@test.local',
            'password' => 'secreto', 'role' => 'cajero', 'active' => true,
        ]);

        $this->actingAs($dueno)->get(route('licencia.estado'))
            ->assertOk()->assertJson(['has_debt' => true, 'pending_count' => 1]);

        $this->actingAs($cajero)->get(route('licencia.estado'))->assertForbidden();
    }
}
