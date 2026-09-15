<?php

namespace Tests\Feature;

use App\Actions\TomarPedido;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Negocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporte: al cargar un pedido con un nombre, cambiaba el nombre de los demás.
 *
 * Pasaba cuando dos pedidos llevaban el mismo teléfono (R-14: el teléfono es
 * la identidad del cliente), algo común con un número de relleno. El pedido
 * mostraba la ficha del cliente, que se pisaba con cada alta.
 */
class ClienteDelPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private array $lineas;

    protected function setUp(): void
    {
        parent::setUp();

        config(['negocio.modulos.delivery' => true]);
        Negocio::olvidar();

        $this->cajero = User::factory()->create(['role' => 'cajero', 'active' => true]);
        CashSession::create(['opened_by' => $this->cajero->id, 'opened_at' => now()]);

        $producto = Product::create([
            'category_id' => Category::create(['name' => 'Comidas', 'active' => true])->id,
            'name' => 'Milanesa', 'price' => 1000, 'goes_to_kitchen' => true,
            'tracks_stock' => false, 'active' => true,
        ]);
        $this->lineas = [['product_id' => $producto->id, 'qty' => 1]];
    }

    private function tomar(string $nombre, string $calle, ?string $detalle = null, string $telefono = '11 5547-5263'): Order
    {
        $orden = app(TomarPedido::class)(
            usuario: $this->cajero, tipo: 'delivery', lineas: $this->lineas,
            telefono: $telefono, nombre: $nombre, calle: $calle, detalle: $detalle,
        );
        $this->normalizarFechas();

        return $orden->load('delivery');
    }

    /**
     * SQLite guarda la fecha con hora y Order::siguienteNumero() no encuentra
     * el pedido anterior. MySQL, con columna DATE, la trunca sola.
     */
    private function normalizarFechas(): void
    {
        \DB::table('orders')->update(['business_date' => today()->toDateString()]);
    }

    public function test_un_pedido_nuevo_con_el_mismo_telefono_no_cambia_los_anteriores(): void
    {
        $primero = $this->tomar('Lali', 'Mitre 100', 'timbre 2');
        $segundo = $this->tomar('Chico', 'Mitre 100', 'timbre 5');

        $this->assertSame(1, Customer::count(), 'El teléfono sigue identificando a un solo cliente.');

        $primero->refresh()->load('delivery.customer');
        // Esto era lo que se mostraba: la ficha compartida ya dice «Chico».
        $this->assertSame('Chico', $primero->delivery->customer->name);
        $this->assertSame('Lali', $primero->delivery->nombreCliente());
        $this->assertSame('Mitre 100, timbre 2', $primero->delivery->direccionCompleta());
        $this->assertSame('Chico', $segundo->delivery->nombreCliente());
        $this->assertSame('Mitre 100, timbre 5', $segundo->delivery->direccionCompleta());

        $tablero = $this->actingAs($this->cajero)->get(route('pedidos'))->assertOk();
        $tablero->assertSeeInOrder(['Lali', 'Chico']);
    }

    public function test_sin_nombre_el_pedido_usa_el_que_ya_tenia_el_cliente(): void
    {
        $this->tomar('Lali', 'Mitre 100');
        $this->normalizarFechas();
        $sinNombre = app(TomarPedido::class)(
            usuario: $this->cajero, tipo: 'retiro', lineas: $this->lineas, telefono: '1155475263',
        )->load('delivery');

        $this->assertSame('Lali', $sinNombre->delivery->nombreCliente());
        $this->assertSame('1155475263', $sinNombre->delivery->telefonoCliente());
    }
}
