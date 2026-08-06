@extends('layouts.app')

@section('titulo', 'Cierre de caja')

@section('topbar')
    <a class="back" href="{{ route('caja') }}"><x-icono nombre="back" /></a>
    <div>
        <h1>Cierre Z · turno del {{ $caja->opened_at->format('d/m/Y') }}</h1>
        <div class="sub">
            {{ $caja->opened_at->format('H:i') }} a {{ $caja->closed_at->format('H:i') }}
            · abrió {{ $caja->openedBy->name }} · cerró {{ $caja->closedBy?->name }}
        </div>
    </div>
    <div class="topbar-actions">
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>
@endsection

@section('contenido')
    <div class="cols">

        <div>
            <div class="card">
                <div class="sec">Movimiento del turno</div>
                <div class="lv"><span class="k">Fondo inicial</span><span class="v">@plata($caja->opening_float)</span></div>
                <div class="lv"><span class="k">Ventas en efectivo</span><span class="v">@plata($caja->ventasPor('cash'))</span></div>
                <div class="lv"><span class="k">Ventas con QR / Mercado Pago</span><span class="v t-mute">@plata($caja->ventasPor('qr'))</span></div>
                <div class="lv"><span class="k">Ventas con transferencia</span><span class="v t-mute">@plata($caja->ventasPor('transfer'))</span></div>
                <div class="lv"><span class="k">Ventas con tarjeta</span><span class="v t-mute">@plata($caja->ventasPor('debit') + $caja->ventasPor('credit'))</span></div>
                @if ($caja->settlements->isNotEmpty())
                    <div class="lv">
                        <span class="k">Rendido por repartidores</span>
                        <span class="v">@plata($caja->rendicionesDeCadetes())</span>
                    </div>
                @endif
                <div class="lv"><span class="k">Gastos</span><span class="v t-red">-@plata($caja->gastos())</span></div>
                <div class="lv"><span class="k">Retiros</span><span class="v t-red">-@plata($caja->retiros())</span></div>

                <div class="hr-strong"></div>
                <div class="lv"><span class="k">Efectivo esperado</span><span class="v money m-sm">@plata($caja->expected_cash)</span></div>
                <div class="lv"><span class="k">Efectivo contado</span><span class="v money m-sm">@plata($caja->counted_cash)</span></div>
            </div>

            @if ($caja->bill_breakdown)
                <div class="card mt16">
                    <div class="sec">Cómo se contó</div>
                    <div class="bills">
                        @foreach (config('negocio.billetes') as $den)
                            @php $cantidad = (int) ($caja->bill_breakdown[(string) $den] ?? 0); @endphp
                            <div class="bill" @style(['opacity:.4' => $cantidad === 0])>
                                <div class="den">@plata($den * 100)</div>
                                <div class="money m-md ta-r mt8">{{ $cantidad }}</div>
                                <div class="sub">@plata($den * 100 * $cantidad)</div>
                            </div>
                        @endforeach
                        <div class="bill">
                            <div class="den">Monedas</div>
                            <div class="money m-md ta-r mt8">—</div>
                            <div class="sub">@plata(\App\Support\Plata::aCentavos($caja->bill_breakdown['monedas'] ?? 0))</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card mt16">
                <div class="sec">Gastos y retiros <span class="meta">{{ $caja->movements->count() }}</span></div>
                @forelse ($caja->movements as $mov)
                    <div class="row">
                        <div class="grow">
                            <div class="nm">{{ $mov->concept }}</div>
                            <div class="sb">{{ $mov->created_at->format('H:i') }} · {{ $mov->user->name }}</div>
                        </div>
                        <span class="pr t-red">-@plata($mov->amount)</span>
                    </div>
                @empty
                    <p class="t-mute fs14">Sin movimientos.</p>
                @endforelse
            </div>
        </div>

        <div>
            @php
                $dif   = $caja->difference;
                $clase = $dif === 0 ? 'callout-green' : ($dif < 0 ? 'callout-red' : '');
                $color = $dif === 0 ? 't-green' : ($dif < 0 ? 't-red' : 't-amber');
            @endphp

            <div class="callout {{ $clase }}">
                <div class="fw6 {{ $color }} mb8">Diferencia de caja</div>
                <div class="money m-xxl {{ $color }}">
                    {{ $dif > 0 ? '+' : '' }}@plata($dif)
                </div>
                <div class="fs13 t-dim mt12">
                    @if ($dif === 0)
                        La caja cuadró exacta.
                    @elseif ($dif < 0)
                        Faltó plata al cerrar.
                    @else
                        Sobró plata al cerrar.
                    @endif
                </div>
            </div>

            @if ($caja->difference_note)
                <div class="pane mt16">
                    <div class="pane-hd"><h3>Explicación</h3></div>
                    <p class="fs14 t-dim">{{ $caja->difference_note }}</p>
                    <div class="fs13 t-mute mt12">Registrado por {{ $caja->closedBy?->name }}.</div>
                </div>
            @endif

            <div class="pane mt16">
                <div class="pane-hd"><h3>Cuentas del turno</h3></div>
                <div class="lv"><span class="k">Pedidos cobrados</span><span class="v">{{ $caja->orders()->where('status', 'paid')->count() }}</span></div>
                <div class="lv"><span class="k">Total facturado</span><span class="v">@plata($caja->orders()->where('status', 'paid')->sum('total'))</span></div>
            </div>

            <div class="notice mt16">
                <span class="dot dot-mute"></span>
                <div>
                    <div class="tt">Este turno está cerrado</div>
                    <div class="ds">No se puede modificar. Para corregir algo, se hace un ajuste en el turno siguiente.</div>
                </div>
            </div>

            <a class="btn btn-primary btn-lg btn-block mt16" href="{{ route('caja') }}">Abrir el próximo turno</a>
        </div>

    </div>
@endsection
