<?php

namespace App\Console\Commands;

use App\Actions\MarcarReventa;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Arma las reventas 1:1 que faltan en una instalación que ya venía andando.
 *
 * `negocio:cargar` ya las deja hechas para las cartas nuevas. Esto es para las
 * bases que se cargaron antes, donde las bebidas quedaron con `tracks_stock`
 * prendido y la receta vacía — o sea, sin descontar nada y apareciendo como
 * pendientes en /stock/recetas.
 *
 * De paso arregla el otro lado del mismo problema: los productos que tienen
 * receta cargada pero el flag apagado, que es peor porque la pantalla los
 * muestra sanos y con margen mientras el stock no se mueve.
 *
 * Muestra lo que va a hacer y no toca nada sin --force.
 */
class ArmarReventas extends Command
{
    protected $signature = 'stock:reventas {--force : Aplicar los cambios}';

    protected $description = 'Arma la reventa 1:1 de lo que no pasa por cocina y no tiene receta';

    public function handle(MarcarReventa $marcar): int
    {
        // Lo que se vende tal cual: no va a cocina y nadie le cargó una receta.
        $candidatos = Product::where('active', true)
            ->where('goes_to_kitchen', false)
            ->doesntHave('recipe')
            ->orderBy('name')
            ->get();

        // Receta cargada con el flag apagado: no descuenta y no lo dice.
        $mudos = Product::has('recipe')->where('tracks_stock', false)->orderBy('name')->get();

        if ($candidatos->isEmpty() && $mudos->isEmpty()) {
            $this->info('No hay nada que armar: todo lo que se vende tal cual ya tiene su insumo.');

            return self::SUCCESS;
        }

        if ($candidatos->isNotEmpty()) {
            $this->newLine();
            $this->line('Se les arma la reventa 1:1 (insumo con el mismo nombre, 1 unidad por venta):');
            $this->table(
                ['Producto', 'Categoría', 'Insumo a crear'],
                $candidatos->map(fn (Product $p) => [$p->name, $p->category->name, $p->name])->all(),
            );
        }

        if ($mudos->isNotEmpty()) {
            $this->newLine();
            $this->warn($mudos->count() . ' producto(s) tienen receta pero NO descuentan stock. Se les prende el flag:');
            $this->line('  ' . $mudos->pluck('name')->join(', '));
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Nada de esto se aplicó. Volvé a correr con --force.');

            return self::SUCCESS;
        }

        $creados = 0;

        foreach ($candidatos as $producto) {
            if ($marcar($producto) !== null) {
                $creados++;
            }
        }

        $prendidos = Product::has('recipe')->where('tracks_stock', false)->update(['tracks_stock' => true]);

        $this->newLine();
        $this->info("{$creados} reventa(s) armadas y {$prendidos} producto(s) que ya no descuentan en silencio.");
        $this->line('Los insumos nuevos quedaron en stock 0 y costo 0: cargalos desde /stock.');

        return self::SUCCESS;
    }
}
