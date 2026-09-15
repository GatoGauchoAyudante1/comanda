{{--
  Aviso de abono impago del sistema. Sólo lo ve el dueño: es una deuda suya con
  quien le hizo el sistema, no algo que el cajero o el mozo tengan que leer.

  El estado se pide acá y no en el controlador porque el panel de atención es de
  la operación del local y no tiene por qué saber de esto. De paso,
  LicenseService::status() crea el cargo del mes si todavía no existía, así el
  aviso aparece aunque el cron del servidor no esté configurado.

  Los importes van en pesos, no en centavos: no usar @plata acá (R-31 es del
  dominio del negocio, y este cobro está afuera).
--}}
@php
    $licencia = auth()->user()?->esDueno()
        ? app(\App\Services\LicenseService::class)->status()
        : null;
@endphp

@if ($licencia && $licencia['has_debt'])
    @php
        $periodos = $licencia['charges']->map(fn ($c) => $c->period_label)->implode(', ');
        $vence    = $licencia['oldest_pending']->due_date->format('d/m/Y');
        $total    = '$' . number_format($licencia['total_due'], 0, ',', '.');
        $pago     = $licencia['payment'];
    @endphp

    <div class="callout {{ $licencia['has_overdue'] ? 'callout-red' : '' }} mb16">
        <div class="fw6 {{ $licencia['has_overdue'] ? 't-red' : '' }} mb8">
            {{ $licencia['has_overdue'] ? 'Abono del sistema vencido' : 'Abono del sistema pendiente' }}
            · {{ $total }}
        </div>

        <div class="fs13 t-dim">
            {{ $licencia['pending_count'] === 1 ? 'Período' : 'Períodos' }}:
            <span style="text-transform:capitalize">{{ $periodos }}</span>.
            {{ $licencia['has_overdue'] ? 'Venció' : 'Vence' }} el {{ $vence }}.

            @if ($pago['alias'])
                <br>
                Transferir al alias <strong>{{ $pago['alias'] }}</strong>
                @if ($pago['method']) ({{ $pago['method'] }}) @endif
                @if ($pago['phone']) e informar el pago al {{ $pago['phone'] }} @endif
            @endif
        </div>
    </div>
@endif
