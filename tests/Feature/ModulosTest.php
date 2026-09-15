<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Negocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El .env dice qué está contratado; Ajustes sólo prende o apaga dentro de eso.
 * Ver docs/02-decisiones.md · D-04.
 */
class ModulosTest extends TestCase
{
    use RefreshDatabase;

    private function contratar(array $modulos): void
    {
        config(['negocio.modulos' => array_merge(
            array_fill_keys(Negocio::MODULOS, false),
            $modulos,
        )]);
        Negocio::olvidar();
    }

    private function dueno(): User
    {
        return User::factory()->create(['role' => 'dueno', 'active' => true]);
    }

    public function test_una_fila_en_settings_no_prende_lo_que_no_esta_contratado(): void
    {
        $this->contratar(['delivery' => false]);
        Setting::put('modules.delivery', '1', 'bool');
        Negocio::olvidar();

        $this->assertFalse(Negocio::modulo('delivery'));
    }

    public function test_lo_contratado_arranca_prendido_y_el_dueno_lo_puede_apagar(): void
    {
        $this->contratar(['delivery' => true, 'pedidos_online' => true]);

        $this->get(route('pedido-online'))->assertOk();

        $this->actingAs($this->dueno())
            ->post(route('configuracion.modulo'), ['modulo' => 'pedidos_online'])
            ->assertSessionHas('ok');

        Negocio::olvidar();
        $this->assertFalse(Negocio::modulo('pedidos_online'));
        $this->get(route('pedido-online'))->assertNotFound();
    }

    public function test_el_dueno_no_puede_prender_un_modulo_no_contratado(): void
    {
        $this->contratar(['delivery' => true, 'pedidos_online' => false]);

        $this->actingAs($this->dueno())
            ->post(route('configuracion.modulo'), ['modulo' => 'pedidos_online'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('settings', ['key' => 'modules.pedidos_online']);
        $this->get(route('pedido-online'))->assertNotFound();
    }

    public function test_pedidos_online_necesita_delivery(): void
    {
        $this->contratar(['delivery' => true, 'pedidos_online' => true]);
        Setting::put('modules.delivery', '0', 'bool');
        Negocio::olvidar();

        $this->assertFalse(Negocio::modulo('pedidos_online'));
        $this->get(route('pedido-online'))->assertNotFound();

        $cajero = User::factory()->create(['role' => 'cajero', 'active' => true]);
        $this->actingAs($cajero)->get(route('pedidos-online'))->assertNotFound();
    }

    public function test_el_link_para_clientes_se_ve_en_ajustes_y_en_el_menu(): void
    {
        $this->contratar(['salon' => true, 'delivery' => true, 'pedidos_online' => true]);

        $this->actingAs($this->dueno())
            ->get(route('configuracion'))
            ->assertOk()
            ->assertSee(route('pedido-online'))
            ->assertSee('Pedidos online');

        $cajero = User::factory()->create(['role' => 'cajero', 'active' => true]);
        $this->actingAs($cajero)
            ->get(route('pedidos-online'))
            ->assertOk()
            ->assertSee('Así piden los clientes')
            ->assertSee(route('pedido-online'));
    }

    public function test_ajustes_muestra_bloqueado_lo_no_contratado(): void
    {
        $this->contratar(['salon' => true]);

        $this->actingAs($this->dueno())
            ->get(route('configuracion'))
            ->assertOk()
            ->assertSee('no incluido en tu plan')
            ->assertDontSee(route('pedido-online'));
    }
}
