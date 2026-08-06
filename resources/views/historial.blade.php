@extends('layouts.app')

@php use App\Models\AuditEvent; @endphp

@section('titulo', 'Historial')

@section('topbar')
    <div>
        <h1>Historial</h1>
        <div class="sub">Todo lo que pasó, con nombre y hora</div>
    </div>
    <div class="topbar-actions">
        @if (collect($filtros)->filter()->isNotEmpty())
            <a class="btn" href="{{ route('historial') }}">Limpiar filtros</a>
        @endif
    </div>
@endsection

@section('contenido')

    <form method="GET" action="{{ route('historial') }}" class="card mb16">
        <div class="grid3">
            <div class="field">
                <label for="buscar">Buscar</label>
                <input id="buscar" class="inp" name="buscar" value="{{ $filtros['buscar'] }}"
                       placeholder="N° de pedido, mesa, cliente, producto…">
                <span class="fs13 t-mute">Un número solo busca ese comprobante.</span>
            </div>

            <div class="field">
                <label for="usuario">Quién</label>
                <select id="usuario" class="inp" name="usuario">
                    <option value="">Cualquiera</option>
                    @foreach ($usuarios as $u)
                        <option value="{{ $u->id }}" @selected($filtros['usuario'] === $u->id)>
                            {{ $u->name }} · {{ $u->role }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="familia">Qué</label>
                <select id="familia" class="inp" name="familia">
                    <option value="">Todo</option>
                    @foreach (AuditEvent::FAMILIAS as $clave => [$nombre, $color])
                        <option value="{{ $clave }}" @selected($filtros['familia'] === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex g10 mt16 wrap">
            <input type="hidden" name="dia" value="{{ $filtros['dia'] }}">
            <button class="btn btn-primary" type="submit">Buscar</button>

            @if ($filtros['dia'])
                <span class="chip chip-green">
                    Día {{ \Illuminate\Support\Carbon::parse($filtros['dia'])->format('d/m/Y') }}
                </span>
            @endif
        </div>
    </form>

    <div class="cat-split">

        {{-- ============ días operativos ============ --}}
        <div class="card card-tight hide-mobile" style="position:sticky;top:0">
            <div class="sec" style="padding:6px 10px 8px;margin:0">Días operativos</div>

            <a class="cat {{ $filtros['dia'] ? '' : 'is-on' }}"
               href="{{ route('historial', collect($filtros)->except('dia')->filter()->all()) }}">
                Todos
            </a>

            @forelse ($dias as $d)
                <a class="cat {{ $filtros['dia'] === $d['fecha']->toDateString() ? 'is-on' : '' }}"
                   href="{{ route('historial', collect($filtros)->filter()->merge(['dia' => $d['fecha']->toDateString()])->all()) }}">
                    {{ $d['fecha']->format('d/m/Y') }}
                    <span class="n">{{ $d['eventos'] }}</span>
                </a>
            @empty
                <p class="t-mute fs14" style="padding:8px 10px">Todavía no hay movimientos.</p>
            @endforelse
        </div>

        {{-- ============ eventos ============ --}}
        <div>
            <div class="card">
                <div class="sec">
                    Movimientos
                    <span class="meta">{{ $eventos->total() }} registrados</span>
                </div>

                @php $fechaAnterior = null; @endphp

                @forelse ($eventos as $e)
                    @php
                        $pedido = $e->subject_type === \App\Models\Order::class
                            ? ($pedidos[$e->subject_id] ?? null)
                            : null;
                        $fecha  = $e->created_at->toDateString();
                    @endphp

                    @if ($fecha !== $fechaAnterior)
                        <div class="sec {{ $loop->first ? 'mt12' : 'mt26' }}">
                            {{ $e->created_at->translatedFormat('l j \d\e F') }}
                        </div>
                        @php $fechaAnterior = $fecha; @endphp
                    @endif

                    <div class="evento">
                        <div class="evento-hora">{{ $e->created_at->format('H:i') }}</div>

                        <div class="evento-linea">
                            <span class="evento-punto punto-{{ $e->color() }}"></span>
                        </div>

                        <div class="evento-cuerpo">
                            <div class="fs15">{{ $e->description }}</div>

                            <div class="flex g8 mt4 wrap">
                                <span class="fs13 t-mute">{{ $e->responsable() }}</span>

                                @if ($pedido)
                                    <span class="fs13 t-mute">·</span>
                                    <a class="fs13 t-green" href="{{ route('historial', ['buscar' => $pedido->number]) }}">
                                        pedido #{{ $pedido->number }}
                                    </a>

                                    @if ($pedido->tableSession?->table)
                                        <span class="chip chip-line">{{ $pedido->tableSession->table->name }}</span>
                                    @elseif ($pedido->type === 'delivery')
                                        <span class="chip chip-line">delivery</span>
                                    @elseif ($pedido->type === 'retiro')
                                        <span class="chip chip-line">retiro</span>
                                    @endif

                                    @if ($pedido->status === 'cancelled')
                                        <span class="chip chip-red">anulado</span>
                                    @elseif ($pedido->status === 'paid')
                                        <span class="chip chip-green">cobrado</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col" style="align-items:center;gap:10px;padding:32px 0">
                        <div class="money m-md t-dim">Sin movimientos</div>
                        <div class="fs14 t-mute">Probá con otros filtros o mirá otro día.</div>
                    </div>
                @endforelse
            </div>

            @if ($eventos->hasPages())
                <div class="mt16">{{ $eventos->links() }}</div>
            @endif

            <div class="notice mt16">
                <span class="dot dot-mute"></span>
                <div>
                    <div class="tt">Este registro no se edita ni se borra</div>
                    <div class="ds">
                        Si algo se hizo mal y después se corrigió, van a figurar las dos cosas.
                        Eso es lo que lo hace servir para resolver un reclamo.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
