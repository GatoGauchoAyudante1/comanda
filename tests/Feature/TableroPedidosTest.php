<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Negocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TableroPedidosTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private Product $producto;
    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['negocio.modulos.delivery' => true, 'negocio.modulos.salon' => true]);
        Negocio::olvidar();

        $this->cajero   = User::factory()->create(['role' => 'cajero', 'active' => true]);
        $categoria      = Category::create(['name' => 'Comidas', 'active' => true]);
        $this->producto = Product::create([
            'category_id' => $categoria->id, 'name' => 'Milanesa', 'price' => 1000,
            'goes_to_kitchen' => true, 'tracks_stock' => false, 'active' => true,
        ]);
    }

    private function pedido(string $tipo, Carbon $alta): Order
    {
        $orden = Order::create([
            'type' => $tipo, 'status' => $tipo === 'delivery' ? 'kitchen' : 'open',
            'number' => ++$this->numero, 'business_date' => today(), 'user_id' => $this->cajero->id,
        ]);
        $orden->forceFill(['created_at' => $alta])->save();

        return $orden;
    }

    private function item(Order $orden, Carbon $enviado): void
    {
        OrderItem::create([
            'order_id' => $orden->id, 'product_id' => $this->producto->id, 'qty' => 1,
            'unit_price' => 1000, 'status' => 'kitchen', 'sent_to_kitchen_at' => $enviado,
        ]);
    }

    public function test_en_cocina_ordena_mezclado_por_espera_real_y_no_por_alta_de_la_mesa(): void
    {
        // Mesa abierta hace 2 horas, pero la comida se pidió hace 5 minutos.
        $mesa = $this->pedido('mesa_salon', now()->subHours(2));
        TableSession::create([
            'table_id' => Table::create(['name' => 'Mesa 7', 'type' => 'salon'])->id,
            'order_id' => $mesa->id, 'user_id' => $this->cajero->id, 'started_at' => now()->subHours(2),
        ]);
        $this->item($mesa, now()->subMinutes(5));

        // Delivery que entró hace 40 minutos: es el más demorado.
        $delivery = $this->pedido('delivery', now()->subMinutes(40));
        $this->item($delivery, now()->subMinutes(40));
        $cliente = Customer::create(['name' => 'Juana Pérez', 'phone' => '11 2345-6789']);
        Delivery::create([
            'order_id' => $delivery->id, 'customer_id' => $cliente->id,
            'customer_name' => $cliente->name, 'customer_phone' => $cliente->phone,
            'address_id' => Address::create(['customer_id' => $cliente->id, 'street' => 'San Martín 1234'])->id,
            'street' => 'San Martín 1234',
        ]);

        $this->assertSame(5, (int) $mesa->load('items')->esperaDesde()->diffInMinutes(now()));

        $respuesta = $this->actingAs($this->cajero)->get(route('pedidos'))->assertOk();

        $columna = $respuesta->viewData('columnas')['kitchen']['pedidos'];
        $this->assertSame([$delivery->id, $mesa->id], $columna->pluck('id')->all());

        // Lo que usa el buscador: nombre, teléfono con y sin formato, dirección.
        $respuesta->assertSee('Juana Pérez 11 2345-6789 1123456789 San Martín 1234', false);
        $respuesta->assertSee('5 min');
        $respuesta->assertDontSee('120 min');
    }

    public function test_el_tablero_no_se_recarga_solo_y_avisa_cuando_algo_cambio(): void
    {
        $pedido = $this->pedido('delivery', now()->subMinutes(3));
        $this->item($pedido, now()->subMinutes(3));
        Delivery::create(['order_id' => $pedido->id]);

        $tablero = $this->actingAs($this->cajero)->get(route('pedidos'))->assertOk();
        $tablero->assertDontSee('location.reload()}', false);
        $firma = $tablero->viewData('firma');

        $this->getJson(route('pedidos.firma'))->assertOk()->assertJson(['firma' => $firma]);

        $pedido->update(['status' => 'ready']);

        $this->assertNotSame($firma, $this->getJson(route('pedidos.firma'))->json('firma'));
    }
}
