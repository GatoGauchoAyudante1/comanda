{{--
  Carga invertida: el insumo viene fijo y se elige el producto.

  Posta al MISMO endpoint que la ficha de receta (RecetaController::guardarLinea),
  en modo `rendimiento`. Lo único que cambia es cuál de los dos extremos está
  fijo; la línea que se guarda es idéntica.
--}}
<div class="overlay" x-show="insumo" x-cloak @click.self="insumo = null" @keydown.escape.window="insumo = null">
    <form class="modal" style="max-width:560px" method="POST"
          :action="'{{ route('receta.linea', ['producto' => '__ID__']) }}'.replace('__ID__', producto)">
        @csrf
        <input type="hidden" name="modo" value="rendimiento">
        <input type="hidden" name="ingredient_id" :value="insumo?.id">

        <div class="modal-hd">
            <div class="grow">
                <h2>Sumar un producto</h2>
                <div class="sub" x-text="insumo?.name"></div>
            </div>
            <button class="xbtn" type="button" @click="insumo = null">&times;</button>
        </div>

        <div class="modal-bd">
            <div class="phrase">
                <div class="line">
                    <span class="t-dim">De un envase de</span>
                    <input class="inp" type="number" step="0.001" min="0.001" name="contenido"
                           x-model.number="contenido" inputmode="decimal" style="width:110px">
                    <select class="inp" name="unidad" x-model="unidad" style="width:auto">
                        <template x-for="u in unidades()" :key="u">
                            <option :value="u" x-text="u"></option>
                        </template>
                    </select>
                    <span class="t-dim">de</span>
                    <span class="fw6" x-text="insumo?.name"></span>
                </div>

                <div class="line">
                    <span class="t-dim">me salen</span>
                    <input class="inp" type="number" step="1" min="1" name="rinde"
                           x-model.number="rinde" inputmode="numeric" style="width:96px">
                    <span class="t-dim">unidades de</span>
                    <select class="inp" x-model="producto" required style="min-width:240px">
                        <option value="">Elegí el producto…</option>
                        @foreach ($destinos as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hr" style="background:var(--green-line);opacity:.4"></div>

                <div class="fs17 t-green fw6" x-show="porUnidad() > 0">
                    ↳ Cada unidad lleva <span x-text="legible(porUnidad())"></span>
                </div>
                <div class="fs13 t-mute" x-show="porUnidad() <= 0">
                    Completá el envase y el rendimiento y te muestro cuánto lleva cada unidad.
                </div>
            </div>

            <label class="half mt16" style="cursor:pointer">
                <input type="checkbox" name="only_for_delivery" value="1"
                       style="width:18px;height:18px;accent-color:var(--green)">
                <div class="grow">
                    <div class="fw6">Sólo en delivery y retiro</div>
                    <div class="fs13 t-mute">Para la caja de pizza, bolsas, descartables.</div>
                </div>
            </label>

            <div class="notice mt16">
                <span class="dot dot-mute"></span>
                <div class="ds">
                    Podés repetirlo para cada producto que lleve este insumo. El mismo
                    envase rinde distinto en cada uno y no se pisan entre sí.
                </div>
            </div>
        </div>

        <div class="modal-ft">
            <div class="grow"></div>
            <button class="btn" type="button" @click="insumo = null">Cancelar</button>
            <button class="btn btn-primary" type="submit" :disabled="!producto || porUnidad() <= 0">
                Agregar a la receta
            </button>
        </div>
    </form>
</div>
