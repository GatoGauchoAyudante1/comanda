<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/**
 * Zonas y clientes de ejemplo. Las zonas reales las define el cliente
 * (ver docs/09-pendientes.md · P-09).
 */
class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        $zonas = [
            ['Centro', 120000],
            ['Barrio Norte', 150000],
            ['Barrio Sarmiento', 180000],
            ['Villa Elisa', 220000],
        ];

        foreach ($zonas as [$nombre, $costo]) {
            Zone::updateOrCreate(
                ['name' => $nombre],
                ['delivery_fee' => $costo, 'active' => true],
            );
        }

        $zonaId = Zone::pluck('id', 'name');

        $clientes = [
            ['Marcela Ortiz',  '1155482210', 'Av. Sarmiento 1482', 'timbre 3B', 'Centro'],
            ['Ramiro Alba',    '1144039876', 'Belgrano 740',       'dpto 2A',   'Barrio Norte'],
            ['Sergio Vallejos','1166721190', 'Mitre 2210',          null,       'Centro'],
            ['Diego Márquez',  '1133887744', 'Los Álamos 355',      null,       'Villa Elisa'],
        ];

        foreach ($clientes as [$nombre, $tel, $calle, $detalle, $zona]) {
            $cliente = Customer::updateOrCreate(
                ['phone' => $tel],
                ['name' => $nombre],
            );

            Address::updateOrCreate(
                ['customer_id' => $cliente->id, 'street' => $calle],
                ['detail' => $detalle, 'zone_id' => $zonaId[$zona], 'is_default' => true],
            );
        }
    }
}
