@props(['orden'])

@php
    /*
     | De dónde viene el pedido y hacia dónde va, sin que haya que deducirlo.
     |
     | La distinción que más se confunde es delivery vs retiro: los dos suelen
     | entrar por teléfono, pero en uno sale un cadete y en el otro el cliente
     | viene a buscarlo. Por eso el texto dice qué pasa, no sólo cómo se llama.
     */
    $entrega = $orden->delivery;
    $mesa    = $orden->tableSession?->table;

    $origen = match ($orden->type) {
        'delivery' => [
            'chip'   => 'chip-blue',
            'titulo' => 'DELIVERY',
            'detalle' => 'Se lo llevamos'
                . ($entrega?->zone ? ' · ' . $entrega->zone->name : '')
                . ($entrega?->address ? ' · ' . $entrega->address->street : ''),
        ],
        'retiro' => [
            'chip'   => 'chip-amber',
            'titulo' => 'RETIRA',
            'detalle' => 'Pasa a buscarlo por el local',
        ],
        'mesa_pool', 'mesa_salon' => [
            'chip'   => 'chip-green',
            'titulo' => mb_strtoupper($mesa?->name ?? 'MESA'),
            'detalle' => 'Se sirve en la mesa'
                . ($orden->tableSession?->user ? ' · atiende ' . $orden->tableSession->user->name : ''),
        ],
        default => [
            'chip'    => 'chip-line',
            'titulo'  => 'MOSTRADOR',
            'detalle' => 'Se entrega en la barra',
        ],
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex g8 wrap']) }}>
    <span class="chip {{ $origen['chip'] }}">{{ $origen['titulo'] }}</span>
    <span class="fs13 t-mute">{{ $origen['detalle'] }}</span>
</div>
