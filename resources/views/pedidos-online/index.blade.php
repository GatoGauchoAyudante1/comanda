@extends('layouts.app')

@section('titulo', 'Pedidos online')
@section('subtitulo', 'Solicitudes pendientes de confirmación')

@section('contenido')
<div class="stats stats-3 mb16">
    <div class="stat"><div class="label">Sin confirmar</div><div class="val">{{ $pedidos->count() }}</div><div class="foot">Requieren respuesta por WhatsApp</div></div>
</div>

<div class="grid3">
    @forelse($pedidos as $pedido)
        <a class="card" href="{{ route('pedidos-online.mostrar', $pedido) }}" style="display:block">
            <div class="between"><span class="chip chip-amber">Pendiente</span><span class="fs13 t-mute">hace {{ $pedido->created_at->diffForHumans(null, true) }}</span></div>
            <h2 class="mt16" style="font-size:20px">#{{ $pedido->id }} · {{ $pedido->customer_name }}</h2>
            <div class="fs14 t-mute mt4">{{ $pedido->fulfillment_type === 'delivery' ? 'Delivery' : 'Retira en el local' }} · {{ $pedido->phone }}</div>
            <div class="row mt12"><span class="qty">{{ $pedido->items->sum('qty') }}</span><div class="grow"><div class="nm">productos</div><div class="sb">{{ $pedido->items->pluck('product_name')->take(2)->join(' · ') }}</div></div><span class="pr">@plata($pedido->total)</span></div>
            <span class="btn btn-primary btn-block mt16">Revisar pedido</span>
        </a>
    @empty
        <div class="card" style="grid-column:1/-1;text-align:center;padding:42px"><div style="font-size:30px;color:var(--green)">✓</div><h2 class="mt12">Todo al día</h2><p class="t-mute mt4">No hay pedidos online esperando confirmación.</p></div>
    @endforelse
</div>
@endsection
