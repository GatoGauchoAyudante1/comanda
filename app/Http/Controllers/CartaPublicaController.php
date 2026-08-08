<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\Negocio;
use Illuminate\Contracts\View\View;

/**
 * La carta que ve el cliente del local, sin usuario ni contraseña.
 *
 * Es la única pantalla del sistema que se sirve a cualquiera que entre, así
 * que muestra exactamente dos cosas: qué hay y cuánto sale. Nada de costos,
 * márgenes, recetas, stock ni mesas.
 *
 * Se apaga desde Ajustes → Carta. Apagada devuelve 404 y no "acceso
 * denegado": si el dueño no la publica, el link no tiene por qué existir.
 */
class CartaPublicaController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(Negocio::cartaPublica(), 404);

        $categorias = Category::query()
            ->where('active', true)
            ->whereHas('products', fn ($q) => $q->where('active', true))
            ->with(['products' => fn ($q) => $q->where('active', true)->with('variants')])
            ->orderBy('sort_order')
            ->get();

        return view('carta-publica', [
            'negocio'    => Negocio::nombre(),
            'mensaje'    => Negocio::cartaMensaje(),
            'categorias' => $categorias,
        ]);
    }
}
