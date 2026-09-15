@extends('dev-panel.layout')

@section('titulo', 'Panel del desarrollador')

@php
    // Importes en pesos y sin centavos: el abono es redondo y así se lee de un vistazo.
    $plata = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
@endphp

@section('contenido')
<div class="wrap">

    <div class="head">
        <div>
            <h1>Panel del desarrollador</h1>
            <div class="sub">Abono mensual del sistema</div>
        </div>
        <form method="POST" action="{{ route('dev-panel.logout') }}">
            @csrf
            <button type="submit" class="btn">Salir</button>
        </form>
    </div>

    @if (session('error') || $errors->any())
        <div class="notice notice-red">
            {{ session('error') ?? 'Revisá los datos del formulario.' }}
            @if ($errors->any())
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if (session('success'))
        <div class="notice notice-green">{{ session('success') }}</div>
    @endif

    {{-- ================= resumen ================= --}}
    <div class="grid grid-3">
        <div class="card kpi">
            <div class="lbl">Adeudado</div>
            <div class="val {{ $summary['total_due'] > 0 ? 't-red' : 't-green' }}">{{ $plata($summary['total_due']) }}</div>
            <div class="foot">{{ $summary['pending_count'] }} período(s) pendiente(s)</div>
        </div>
        <div class="card kpi">
            <div class="lbl">Cobrado</div>
            <div class="val">{{ $plata($summary['collected_total']) }}</div>
            <div class="foot">{{ $summary['paid_count'] }} período(s) pagado(s)</div>
        </div>
        <div class="card kpi">
            <div class="lbl">Abono actual</div>
            <div class="val">{{ $plata($settings['monthly_amount']) }}</div>
            <div class="foot">Aviso el día {{ $settings['notice_day'] }} · vence el {{ $settings['due_day'] }}</div>
        </div>
    </div>

    {{-- ================= configuración ================= --}}
    <div class="sec">Configuración</div>
    <div class="hint">Aplica a los meses que se generen de acá en adelante. Los cargos ya creados se editan desde la tabla.</div>

    <div class="card">
        <form method="POST" action="{{ route('dev-panel.settings') }}">
            @csrf
            @method('PUT')

            <div class="fields">
                <div>
                    <label for="monthly_amount">Importe mensual</label>
                    <input type="number" step="0.01" min="0" id="monthly_amount" name="monthly_amount"
                           value="{{ old('monthly_amount', $settings['monthly_amount']) }}">
                </div>
                <div>
                    <label for="notice_day">Día de aviso</label>
                    <input type="number" min="1" max="28" id="notice_day" name="notice_day"
                           value="{{ old('notice_day', $settings['notice_day']) }}">
                </div>
                <div>
                    <label for="due_day">Día de vencimiento</label>
                    <input type="number" min="1" max="28" id="due_day" name="due_day"
                           value="{{ old('due_day', $settings['due_day']) }}">
                </div>
                <div class="check">
                    {{-- El hidden manda el 0 cuando el checkbox va destildado: si no, `enabled` no llega. --}}
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                    <label for="enabled" style="margin:0">Aviso activo</label>
                </div>
            </div>

            <div class="sec">Datos de cobro del aviso</div>
            <div class="hint">Es lo que ve el cliente en el banner y en la notificación.</div>

            <div class="fields">
                <div>
                    <label for="payee">Cobra</label>
                    <input type="text" id="payee" name="payee" value="{{ old('payee', $settings['payee']) }}">
                </div>
                <div>
                    <label for="payment_alias">Alias</label>
                    <input type="text" id="payment_alias" name="payment_alias" value="{{ old('payment_alias', $settings['payment_alias']) }}">
                </div>
                <div>
                    <label for="payment_method">Medio de pago</label>
                    <input type="text" id="payment_method" name="payment_method" value="{{ old('payment_method', $settings['payment_method']) }}">
                </div>
                <div>
                    <label for="contact_phone">Informar el pago al</label>
                    <input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}">
                </div>
            </div>

            <div class="actions">
                {{-- Los forms no se pueden anidar: el botón queda acá y `form=`
                     lo manda al de abajo, que es un POST distinto. --}}
                <button type="submit" class="btn" form="form-generar">Generar cargo del mes</button>
                <button type="submit" class="btn btn-primary">Guardar configuración</button>
            </div>
        </form>

        {{-- Generar el cargo del mes no tiene nada que ver con guardar la configuración. --}}
        <form method="POST" action="{{ route('dev-panel.generate') }}" id="form-generar">@csrf</form>
    </div>

    {{-- ================= períodos ================= --}}
    <div class="sec">Períodos</div>

    <div class="card">
        @if ($charges->isEmpty())
            <div class="empty">Todavía no hay períodos generados</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Importe</th>
                        <th>Vence</th>
                        <th>Estado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($charges as $cargo)
                        <tr>
                            <td style="text-transform:capitalize">{{ $cargo->period_label }}</td>
                            <td class="money">{{ $plata($cargo->amount) }}</td>
                            <td class="money">{{ $cargo->due_date->format('d/m/Y') }}</td>
                            <td>
                                @if ($cargo->status === 'paid')
                                    <span class="badge badge-green">Pagado</span>
                                    <div class="small">
                                        {{ $cargo->paid_at?->format('d/m/Y') }}
                                        @if ($cargo->paid_method) · {{ $cargo->paid_method }} @endif
                                    </div>
                                @elseif ($cargo->is_overdue)
                                    <span class="badge badge-red">Vencido</span>
                                @else
                                    <span class="badge badge-amber">Pendiente</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    @if ($cargo->status === 'paid')
                                        <form method="POST" action="{{ route('dev-panel.charges.unpay', $cargo) }}"
                                              onsubmit="return confirm('¿Volver a pendiente el abono de {{ $cargo->period_label }}?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm">Volver a pendiente</button>
                                        </form>
                                    @else
                                        {{-- El importe se edita con un prompt: es un caso de una vez cada tanto
                                             y no justifica un modal ni JS de más en un panel sin build. --}}
                                        <form method="POST" action="{{ route('dev-panel.charges.update', $cargo) }}"
                                              id="editar-{{ $cargo->id }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="amount" value="{{ $cargo->amount }}">
                                            <button type="button" class="btn btn-sm"
                                                    data-form="editar-{{ $cargo->id }}"
                                                    data-periodo="{{ $cargo->period_label }}"
                                                    onclick="editarImporte(this)">Editar importe</button>
                                        </form>

                                        <form method="POST" action="{{ route('dev-panel.charges.pay', $cargo) }}"
                                              onsubmit="return confirm('¿Marcar como pagado el abono de {{ $cargo->period_label }}?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">Marcar pagado</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>

<script>
    function editarImporte(boton) {
        var form   = document.getElementById(boton.dataset.form);
        var campo  = form.querySelector('input[name="amount"]');
        var valor  = window.prompt('Importe del abono de ' + boton.dataset.periodo + ':', campo.value);

        if (valor === null || valor.trim() === '') {
            return;
        }

        campo.value = valor.trim();
        form.submit();
    }
</script>
@endsection
