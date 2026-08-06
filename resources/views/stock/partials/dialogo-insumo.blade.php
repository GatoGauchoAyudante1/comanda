{{-- Alta y edición de insumo --}}
<div class="overlay" x-show="insumo" x-cloak @click.self="insumo = null" @keydown.escape.window="insumo = null">
    <form class="modal" style="max-width:520px" method="POST"
          :action="insumo?.id
              ? '{{ route('stock.insumo.actualizar', ['insumo' => '__ID__']) }}'.replace('__ID__', insumo.id)
              : '{{ route('stock.insumo') }}'">
        @csrf

        <div class="modal-hd">
            <div class="grow">
                <h2 x-text="insumo?.id ? 'Editar insumo' : 'Nuevo insumo'"></h2>
                <div class="sub" x-show="insumo?.id" x-text="insumo?.name"></div>
            </div>
            <button class="xbtn" type="button" @click="insumo = null">&times;</button>
        </div>

        <div class="modal-bd">
            <div class="modal-sec">
                <div class="field">
                    <label for="i-name">Nombre</label>
                    <input id="i-name" class="inp" name="name" x-model="insumo.name" required maxlength="120"
                           placeholder="Queso muzzarella">
                </div>
            </div>

            <div class="modal-sec grid2">
                <div class="field">
                    <label for="i-unit">Se mide en</label>
                    <select id="i-unit" class="inp" name="base_unit" x-model="insumo.base_unit">
                        <option value="g">Peso (gramos y kilos)</option>
                        <option value="ml">Volumen (ml y litros)</option>
                        <option value="un">Unidades</option>
                    </select>
                </div>
                <div class="field">
                    <label for="i-area">Área</label>
                    <select id="i-area" class="inp" name="area" x-model="insumo.area">
                        <option value="cocina">Cocina</option>
                        <option value="barra">Barra</option>
                        <option value="descartables">Descartables</option>
                    </select>
                </div>
            </div>

            <div class="modal-sec grid3">
                <div class="field">
                    <label for="i-min">Stock mínimo</label>
                    <input id="i-min" class="inp" type="number" step="0.01" min="0" name="min_stock"
                           required inputmode="decimal" placeholder="10">
                </div>
                <div class="field">
                    <label for="i-cost">Costo</label>
                    <input id="i-cost" class="inp" type="number" step="0.01" min="0" name="cost"
                           required inputmode="decimal" placeholder="8900">
                </div>
                <div class="field">
                    <label for="i-unidad">Por</label>
                    <select id="i-unidad" class="inp" name="unidad">
                        <template x-for="u in unidadesDe(insumo?.base_unit ?? 'g')" :key="u">
                            <option :value="u" x-text="u"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="notice mt16">
                <span class="dot dot-mute"></span>
                <div class="ds">
                    Cargá el costo como lo compras: si la horma sale $8.900 el kilo,
                    poné 8900 y elegí «kg». El sistema lo convierte solo.
                </div>
            </div>
        </div>

        <div class="modal-ft">
            <div class="grow"></div>
            <button class="btn" type="button" @click="insumo = null">Cancelar</button>
            <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
    </form>
</div>
