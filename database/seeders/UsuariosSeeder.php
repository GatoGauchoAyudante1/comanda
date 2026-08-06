<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['name' => 'Juan',    'email' => 'juan@local.test',    'role' => 'dueno'],
            ['name' => 'Walter',  'email' => 'walter@local.test',  'role' => 'mozo'],
            ['name' => 'Sofía',   'email' => 'sofia@local.test',   'role' => 'mozo'],
            ['name' => 'Carla',   'email' => 'carla@local.test',   'role' => 'cajero'],
            ['name' => 'Cocina',  'email' => 'cocina@local.test',  'role' => 'cocina'],
            ['name' => 'Nico',    'email' => 'nico@local.test',    'role' => 'repartidor'],
            ['name' => 'Facu',    'email' => 'facu@local.test',    'role' => 'repartidor'],
        ];

        foreach ($usuarios as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [...$u, 'password' => Hash::make('clave1234'), 'active' => true],
            );
        }

        /*
         | Los MÓDULOS no se siembran a propósito.
         |
         | Una fila en `settings` pisa al .env (ver App\Support\Negocio), así que
         | sembrarlos dejaría el .env sin efecto desde el primer día: alguien
         | cambiaría NEGOCIO_MODULO_POOL y no pasaría nada.
         |
         | Los módulos viven en el .env hasta que el dueño los toque desde
         | Ajustes, que es cuando recién ahí se crea la fila.
         */
        Setting::put('business.name', config('negocio.nombre'));
        Setting::put('receipt.point_of_sale', config('negocio.comprobante.punto_venta'));
    }
}
