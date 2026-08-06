<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Crea un usuario desde la consola.
 *
 * Existe para no tener que correr los seeders de demo en producción: esos
 * crean siete cuentas con una clave conocida. Con este comando se crea el
 * usuario del dueño y desde Ajustes se dan de alta los demás.
 *
 *   php artisan negocio:usuario
 *   php artisan negocio:usuario --name=Juan --email=juan@... --role=dueno
 */
class CrearUsuario extends Command
{
    protected $signature = 'negocio:usuario
                            {--name= : Nombre}
                            {--email= : Correo con el que entra}
                            {--role= : dueno|cajero|mozo|cocina|repartidor}
                            {--password= : Clave, mínimo 8 caracteres}';

    protected $description = 'Crea un usuario del sistema';

    public function handle(): int
    {
        $datos = [
            'name'     => $this->option('name')     ?: $this->ask('Nombre'),
            'email'    => $this->option('email')    ?: $this->ask('Correo con el que entra'),
            'role'     => $this->option('role')     ?: $this->choice('Rol', User::ROLES, 0),
            'password' => $this->option('password') ?: $this->secret('Clave (mínimo 8 caracteres)'),
        ];

        $validador = Validator::make($datos, [
            'name'     => ['required', 'string', 'max:60'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'role'     => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::create([
            ...$datos,
            'password' => Hash::make($datos['password']),
            'active'   => true,
        ]);

        $this->info("Usuario «{$usuario->name}» creado con rol {$usuario->role}.");

        if ($usuario->role !== 'dueno') {
            $this->line('Recordá que sólo el rol «dueno» entra a Ajustes, Carta, Stock y Reportes.');
        }

        return self::SUCCESS;
    }
}
