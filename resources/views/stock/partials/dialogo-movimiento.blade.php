@php
    $esCompra = $tipo === 'compra';
    $ruta     = $esCompra ? route('stock.compra') : route('stock.merma');
@endphp

<div class="overlay" x-show="{{ $tipo }}" x-cloak
     @click.self="{{ $tipo }} = false" @keydown.escape.window="{{ $tipo }} = false">
    <form class="modal" style="max-width:480px" method="POST" action="{{ $ruta }}">
        @csrf

        <div class="modal-hd">
            <div class="grow">
                <h2>{{ $esCompra ? 'Registrar compra' : 'Registrar merma' }}</h2>
                <div class="sub">
                    {{ $esCompra
                        ? 'Suma stock y actualiza el costo'
                        : 'Descuenta stock por rotura o desperdicio' }}
                </div>
            </div>
            <button class="xbtn" type="button" @click="{{ $tipo }} = false">&times;</button>
        </div>

        <div class="modal-bd">
            <div class="modal-sec">
                <div class="field">
                    <label for="{{ $tipo }}-ing">Insumo</label>
                    <select id="{{ $tipo }}-ing" class="inp" name="ingredient_id" x-model="elegido" required>
                        <option value="">Elegí uno…</option>
                        @foreach ($insumos as $i)
                            <option value="{{ $i->id }}">{{ $i->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="modal-sec grid2">
                <div class="field">
                    <label for="{{ $tipo }}-cant">Cantidad</label>
                    <input id="{{ $tipo }}-cant" class="inp inp-lg" type="number" step="0.001" min="0.001"
                           name="cantidad" required inputmode="decimal">
                </div>
                <div class="field">
                    <label for="{{ $tipo }}-un">Unidad</label>
                    <select id="{{ $tipo }}-un" class="inp inp-lg" name="unidad" required>
                        <template x-for="u in unidadesDelElegido()" :key="u">
                            <option :value="u" x-text="u"></option>
                        </template>
                    </select>
                </div>
            </div>

            @if ($esCompra)
                <div class="modal-sec">
                    <div class="field">
                        <label for="compra-total">Cuánto pagaste en total</label>
                        <input id="compra-total" class="inp inp-lg" type="number" step="0.01" min="0"
                               name="total" required inputmode="decimal" placeholder="71200">
                        <span class="fs13 t-mute">
                            El costo unitario se recalcula con este precio (último precio de compra).
                        </span>
                    </div>
                </div>
            @else
                <div class="modal-sec">
                    <div class="field">
                        <label for="merma-motivo">Motivo</label>
                        <textarea id="merma-motivo" class="inp" name="reason" required minlength="4" maxlength="200"
                                  placeholder="Se cayó una caja, venció, se quemó en la plancha…"></textarea>
                        <span class="fs13 t-mute">Obligatorio: queda registrado con tu usuario.</span>
                    </div>
                </div>
            @endif
        </div>

        <div class="modal-ft">
            <div class="grow"></div>
            <button class="btn" type="button" @click="{{ $tipo }} = false">Cancelar</button>
            <button class="btn {{ $esCompra ? 'btn-primary' : 'btn-danger' }}" type="submit">
                {{ $esCompra ? 'Cargar compra' : 'Registrar merma' }}
            </button>
        </div>
    </form>
</div>
