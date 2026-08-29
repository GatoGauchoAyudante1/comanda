<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Negocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoOnlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'menu.public', 'value' => '1', 'type' => 'bool']);
        Setting::create(['key' => 'modules.delivery', 'value' => '1', 'type' => 'bool']);
        Negocio::olvidar();
    }

    public function test_cliente_crea_pedido_y_cajero_lo_confirma_a_cocina(): void
    {
        $categoria = Category::create(['name' => 'Comidas', 'active' => true]);
        $producto = Product::create([
            'category_id' => $categoria->id,
            'name' => 'Milanesa',
            'price' => 125000,
            'goes_to_kitchen' => true,
            'tracks_stock' => false,
            'active' => true,
        ]);

        $this->get(route('pedido-online'))->assertOk()->assertSee('Milanesa');

        $respuesta = $this->post(route('pedido-online.guardar'), [
            'type' => 'retiro',
            'telefono' => '5491112345678',
            'nombre' => 'Ana',
            'metodo_pago' => 'cash',
            'lineas' => [[
                'product_id' => $producto->id,
                'qty' => 2,
                'notes' => 'Sin sal',
            ]],
        ]);

        $online = OnlineOrder::with('items')->firstOrFail();
        $respuesta->assertRedirect(route('pedido-online.recibido', $online->uuid));
        $this->assertSame('pending', $online->status);
        $this->assertSame(250000, $online->total);
        $this->assertSame('Sin sal', $online->items->first()->notes);

        $cajero = User::factory()->create(['role' => 'cajero', 'active' => true]);
        CashSession::create(['opened_by' => $cajero->id, 'opened_at' => now()]);

        $confirmacion = $this->actingAs($cajero)->post(route('pedidos-online.confirmar', $online), [
            'estimated_minutes' => 35,
            'mensaje' => 'Tu pedido estará listo en {minutos} minutos.',
        ]);

        $confirmacion->assertRedirectContains('https://wa.me/5491112345678');
        $online->refresh();
        $this->assertSame('accepted', $online->status);
        $this->assertSame('Tu pedido estará listo en 35 minutos.', $online->response_message);
        $this->assertSame('kitchen', Order::findOrFail($online->order_id)->status);
        $this->assertDatabaseHas('order_items', ['order_id' => $online->order_id, 'notes' => 'Sin sal']);
    }

    public function test_cajero_puede_rechazar_con_motivo_editable(): void
    {
        $cajero = User::factory()->create(['role' => 'cajero', 'active' => true]);
        $online = OnlineOrder::create([
            'uuid' => fake()->uuid(), 'status' => 'pending', 'fulfillment_type' => 'retiro',
            'phone' => '5491111111111', 'customer_name' => 'Juan', 'total' => 1000,
        ]);

        $this->actingAs($cajero)->post(route('pedidos-online.rechazar', $online), [
            'motivo' => 'Cocina cerrada',
            'mensaje' => 'No podemos tomarlo: {motivo}.',
        ])->assertRedirectContains('https://wa.me/5491111111111');

        $this->assertDatabaseHas('online_orders', [
            'id' => $online->id,
            'status' => 'rejected',
            'rejection_reason' => 'Cocina cerrada',
            'response_message' => 'No podemos tomarlo: Cocina cerrada.',
        ]);
    }
}
