<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\TableRate;
use Illuminate\Database\Seeder;

class MesasSeeder extends Seeder
{
    public function run(): void
    {
        // Asunción P-08 (docs/09-pendientes.md): 8 de pool y 8 de salón.
        foreach (range(1, 8) as $n) {
            Table::updateOrCreate(
                ['name' => "Pool {$n}"],
                ['type' => 'pool', 'sort_order' => $n, 'active' => true],
            );
        }

        foreach (range(1, 8) as $n) {
            Table::updateOrCreate(
                ['name' => "Mesa {$n}"],
                ['type' => 'salon', 'sort_order' => $n, 'active' => true],
            );
        }

        // Tarifas de pool. Los importes van en centavos (R-31).
        $tarifas = [
            ['name' => 'Hora normal',   'price_per_hour' => 400000, 'is_default' => true],
            ['name' => 'Happy hour',    'price_per_hour' => 280000, 'is_default' => false],
            ['name' => 'Fin de semana', 'price_per_hour' => 500000, 'is_default' => false],
        ];

        foreach ($tarifas as $t) {
            TableRate::updateOrCreate(
                ['name' => $t['name']],
                [...$t, 'rounding_minutes' => 30, 'active' => true],
            );
        }
    }
}
