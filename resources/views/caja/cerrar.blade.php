@extends('layouts.app')

@section('titulo', 'Caja')

@section('topbar')
    <div>
        <h1>Caja del turno</h1>
        <div class="sub">
            Abrió {{ $caja->openedBy->name }} el {{ $caja->opened_at->format('d/m') }}
            a las {{ $caja->opened_at->format('H:i') }}
            · {{ $caja->opened_at->diffForHumans(null, true) }}
        </div>
    </div>
@endsection

@section('contenido')
<div x-data="arqueo({{ $caja->efectivoEsperado() }}, @json(config('negocio.billetes')))">

    <form method="POST" action="{{ route('caja.cerrar') }}" id="cierre">@csrf</form>

    <div class="cols">

        <div>
            <div class="card">
                <div class="sec">Lo que dice el sistema</div>
                <div class="lv"><span class="k">Fondo inicial</span><span class="v">@plata($caja->opening_float)</span></div>
                <div class="lv"><span class="k">Ventas en efectivo</span><span class="v">@plata($caja->ventasPor('cash'))</span></div>
                <div class="lv"><span class="k">Ventas con QR / Mercado Pago</span><span class="v t-mute">@plata($caja->ventasPor('qr'))</span></div>
                <div class="lv"><span class="k">Ventas con tarjeta</span><span class="v t-mute">@plata($caja->ventasPor('debit') + $caja->ventasPor('credit'))</span></div>
                @if ($caja->efectivoEnLaCalle() > 0)
                    <div class="lv">
                        <span class="k">
                            En poder de repartidores
                            <span class="fs13 t-mute">· todavía no rindieron</span>
                        </span>
                        <span class="v t-amber">-@plata($caja->efectivoEnLaCalle())</span>
                    </div>
                @endif
                <div class="lv"><span class="k">Gastos del turno</span><span class="v t-red">-@plata($caja->gastos())</span></div>
                <div class="lv"><span class="k">Retiros</span><span class="v t-red">-@plata($caja->retiros())</span></div>
                <div class="hr-strong"></div>
                <div class="between">
                    <span class="t-dim">Efectivo que debería haber</span>
                    <span class="money m-xl">@plata($caja->efectivoEsperado())</span>
                </div>
                <div class="fs13 t-mute mt8">
                    Sólo se arquea el efectivo. QR y tarjeta se concilian con el resumen del banco.
                </div>
            </div>

            {{-- ============ conteo de billetes ============ --}}
            <div class="card mt16">
                <div class="sec">
                    Conteo de billetes
                    <span class="meta">Contado: <span x-text="pesos(contado)"></span></span>
                </div>

                <div class="bills">
                    @foreach (config('negocio.billetes') as $denominacion)
                        <div class="bill">
                            <div class="den">@plata($denominacion * 100)</div>
                            <input class="in" type="number" min="0" step="1" inputmode="numeric"
                                   form="cierre" name="conteo[{{ $denominacion }}]"
                                   x-model.number="conteo[{{ $denominacion }}]" placeholder="0">
                            <div class="sub" x-text="pesos({{ $denominacion }} * 100 * (conteo[{{ $denominacion }}] || 0))"></div>
                        </div>
                    @endforeach

                    <div class="bill">
                        <div class="den">Monedas</div>
                        <input class="in" type="number" min="0" step="1" inputmode="numeric"
                               form="cierre" name="conteo[monedas]"
                               x-model.number="conteo.monedas" placeholder="0">
                        <div class="sub">en pesos</div>
                    </div>
                </div>
            </div>

            <div class="card mt16">
                <div class="sec">
                    Gastos y retiros
                    <span class="meta">{{ $caja->movements->count() }} movimientos</span>
                </div>

                @forelse ($caja->movements as $mov)
                    <div class="row">
                        <div class="grow">
                            <div class="nm">{{ $mov->concept }}</div>
                            <div class="sb">
                                {{ $mov->created_at->format('H:i') }} · {{ $mov->user->name }}
                                · {{ ['expense' => 'gasto', 'withdrawal' => 'retiro', 'deposit' => 'ingreso'][$mov->type] }}
                            </div>
                        </div>
                        <span class="pr {{ $mov->type === 'deposit' ? 't-green' : 't-red' }}">
                            {{ $mov->type === 'deposit' ? '+' : '-' }}@plata($mov->amount)
                        </span>
                    </div>
                @empty
                    <p class="t-mute fs14">Todavía no se registró ningún movimiento.</p>
                @endforelse

                <form method="POST" action="{{ route('caja.movimiento') }}" class="mt16">
                    @csrf
                    <div class="grid3">
                        <div class="field">
                            <label for="concept">Concepto</label>
                            <input id="concept" class="inp inp-sm" name="concept" required
                                   placeholder="Hielo, changa, garrafa…">
                        </div>
                        <div class="field">
                            <label for="amount">Importe</label>
                            <input id="amount" class="inp inp-sm" type="number" step="0.01" min="0.01"
                                   name="amount" required inputmode="decimal" placeholder="6500">
                        </div>
                        <div class="field">
                            <label for="type">Tipo</label>
                            <select id="type" class="inp inp-sm" name="type">
                                <option value="expense">Gasto</option>
                                <option value="withdrawal">Retiro a caja fuerte</option>
                                <option value="deposit">Ingreso de plata</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-dashed btn-block mt12" type="submit">+ Registrar movimiento</button>
                </form>
            </div>
        </div>

        <div>
            <div class="pane">
                <div class="pane-hd"><h3>Antes de cerrar</h3></div>

                @forelse ($pendientes as $aviso)
                    <div class="notice notice-amber mb12">
                        <span class="dot dot-amber"></span>
                        <div class="ds t-white">{{ $aviso }}</div>
                    </div>
                @empty
                    <div class="notice notice-green">
                        <span class="dot"></span>
                        <div>
                            <div class="tt">Todo en orden</div>
                            <div class="ds">No quedan mesas abiertas, pedidos sin entregar ni cadetes sin rendir.</div>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="pane">
                <div class="pane-hd"><h3>Diferencia</h3></div>
                <div class="callout" :class="{ 'callout-green': diferencia === 0, 'callout-red': diferencia < 0 }">
                    <div class="money m-xxl"
                         :class="diferencia === 0 ? 't-green' : (diferencia < 0 ? 't-red' : 't-amber')"
                         x-text="pesos(diferencia)"></div>
                    <div class="fs13 t-dim mt12" x-show="diferencia === 0">
                        La caja cuadra exacta.
                    </div>
                    <div class="fs13 t-dim mt12" x-show="diferencia < 0">
                        Falta plata. Puede ser un vuelto mal dado o un consumo sin cargar.
                    </div>
                    <div class="fs13 t-dim mt12" x-show="diferencia > 0">
                        Sobra plata. Revisá si quedó algún cobro sin registrar.
                    </div>
                </div>

                <div class="lv mt16"><span class="k">Debería haber</span><span class="v">@plata($caja->efectivoEsperado())</span></div>
                <div class="lv"><span class="k">Contaste</span><span class="v" x-text="pesos(contado)"></span></div>
            </div>

            <div class="pane">
                <div class="field">
                    <label for="difference_note">Explicá la diferencia</label>
                    <textarea id="difference_note" class="inp" name="difference_note" form="cierre"
                              maxlength="500" placeholder="Opcional, pero queda registrado con tu usuario."></textarea>
                </div>

                <button class="btn btn-primary btn-lg btn-block mt16" type="submit" form="cierre"
                        @click="return confirm('Una vez cerrado, el turno no se puede modificar. ¿Cerrás?')">
                    Confirmar cierre de caja
                </button>

                <div class="fs13 t-mute mt12 ta-r">Un turno cerrado no se modifica.</div>
            </div>
        </div>

    </div>
</div>

<script>
function arqueo(esperado, denominaciones) {
    return {
        esperado,
        conteo: Object.fromEntries([...denominaciones.map(d => [d, null]), ['monedas', null]]),

        // Todo en centavos, como en el resto del sistema (R-31).
        get contado() {
            const billetes = denominaciones.reduce(
                (n, d) => n + d * 100 * (this.conteo[d] || 0), 0);

            return billetes + (this.conteo.monedas || 0) * 100;
        },

        get diferencia() { return this.contado - this.esperado; },

        pesos(centavos) {
            const signo = centavos > 0 ? '+' : (centavos < 0 ? '-' : '');
            return signo + '$' + Math.abs(centavos / 100).toLocaleString('es-AR', { maximumFractionDigits: 0 });
        },
    };
}
</script>
@endsection
