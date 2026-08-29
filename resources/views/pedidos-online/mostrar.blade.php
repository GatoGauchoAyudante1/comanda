@extends('layouts.app')

@section('titulo', 'Pedido online #' . $pedido->id)
@section('topbar')
    <a class="back" href="{{ route('pedidos-online') }}"><x-icono nombre="back" /></a>
    <div><h1>Pedido online #{{ $pedido->id }}</h1><div class="sub">Recibido {{ $pedido->created_at->format('H:i') }} · {{ $pedido->created_at->diffForHumans() }}</div></div>
@endsection

@section('contenido')
<div class="cols">
    <div>
        <div class="card">
            <div class="between"><div class="sec" style="margin:0">Cliente</div><span class="chip {{ $pedido->status === 'pending' ? 'chip-amber' : ($pedido->status === 'accepted' ? 'chip-green' : 'chip-red') }}">{{ match($pedido->status){'pending'=>'Pendiente','accepted'=>'Confirmado','rejected'=>'Rechazado',default=>$pedido->status} }}</span></div>
            <div class="grid2 mt16"><div><div class="fs13 t-mute">Nombre</div><div class="fw6 mt4">{{ $pedido->customer_name }}</div></div><div><div class="fs13 t-mute">WhatsApp</div><div class="fw6 mt4">{{ $pedido->phone }}</div></div></div>
            <div class="hr"></div>
            <div class="fs13 t-mute">Entrega</div><div class="fw6 mt4">{{ $pedido->fulfillment_type === 'delivery' ? 'Delivery' : 'Retira por el local' }}</div>
            @if($pedido->fulfillment_type === 'delivery')<div class="mt8">{{ $pedido->street }}@if($pedido->address_detail), {{ $pedido->address_detail }}@endif @if($pedido->zone)<span class="t-mute">· {{ $pedido->zone->name }}</span>@endif</div>@endif
            @if($pedido->notes)<div class="notice notice-amber mt16"><span class="dot dot-amber"></span><div><div class="tt">Observación general</div><div class="ds">{{ $pedido->notes }}</div></div></div>@endif
        </div>

        <div class="card mt16"><div class="sec">Detalle</div>
            @foreach($pedido->items as $item)<div class="row"><span class="qty">{{ $item->qty }}</span><div class="grow"><div class="nm">{{ $item->product_name }} @if($item->variant_name){{ $item->variant_name }}@endif</div>@if($item->notes)<div class="sb t-amber">{{ $item->notes }}</div>@endif<div class="sb">@plata($item->unit_price) c/u</div></div><span class="pr">@plata($item->subtotal())</span></div>@endforeach
            <div class="lv mt12"><span class="k">Productos</span><span class="v">@plata($pedido->items_total)</span></div>@if($pedido->delivery_fee)<div class="lv"><span class="k">Envío</span><span class="v">@plata($pedido->delivery_fee)</span></div>@endif<div class="hr-strong"></div><div class="between"><span>Total</span><span class="money m-xl">@plata($pedido->total)</span></div>
            <div class="notice mt16"><span class="dot dot-mute"></span><div><div class="tt">{{ match($pedido->payment_method){'cash'=>'Paga en efectivo','qr'=>'Paga con QR / transferencia','debit'=>'Paga con débito','credit'=>'Paga con crédito',default=>'Medio de pago a definir'} }}</div>@if($pedido->pays_with)<div class="ds">Paga con @plata($pedido->pays_with)</div>@endif</div></div>
        </div>
    </div>

    <div>
        @if($pedido->status === 'pending')
            <form class="pane" method="POST" action="{{ route('pedidos-online.confirmar', $pedido) }}">@csrf<div class="pane-hd"><h3>Confirmar pedido</h3></div><div class="field"><label>Demora estimada (minutos)</label><input class="inp inp-lg" type="number" name="estimated_minutes" min="1" max="480" value="30" required inputmode="numeric"></div><div class="field mt16"><label>Mensaje de WhatsApp</label><textarea class="inp" name="mensaje" maxlength="1000" rows="6" required>{{ $mensajeConfirmacion }}</textarea><div class="fs12 t-mute">Usá <strong>{minutos}</strong> para insertar la demora.</div></div><button class="btn btn-primary btn-lg btn-block mt16" type="submit">Confirmar y abrir WhatsApp</button><p class="fs12 t-mute mt8">Al confirmar, el pedido pasa directamente a Pedidos y Cocina.</p></form>
            <form class="pane" method="POST" action="{{ route('pedidos-online.rechazar', $pedido) }}">@csrf<div class="pane-hd"><h3>Rechazar pedido</h3></div><div class="field"><label>Motivo</label><input class="inp" name="motivo" maxlength="300" required placeholder="Ej.: producto sin stock"></div><div class="field mt16"><label>Mensaje de WhatsApp</label><textarea class="inp" name="mensaje" maxlength="1000" rows="5" required>{{ $mensajeRechazo }}</textarea><div class="fs12 t-mute">Usá <strong>{motivo}</strong> para insertar la explicación.</div></div><button class="btn btn-danger btn-block mt16" type="submit">Rechazar y abrir WhatsApp</button></form>
        @else
            <div class="pane"><div class="pane-hd"><h3>Pedido respondido</h3></div><div class="notice {{ $pedido->status === 'accepted' ? 'notice-green' : 'notice-amber' }}"><span class="dot {{ $pedido->status === 'accepted' ? '' : 'dot-amber' }}"></span><div><div class="tt">{{ $pedido->status === 'accepted' ? 'Confirmado' : 'Rechazado' }}</div><div class="ds">{{ $pedido->responded_at?->format('d/m/Y H:i') }} por {{ $pedido->responder?->name }}</div></div></div>@if($pedido->response_message)<div class="field mt16"><label>Mensaje enviado</label><div class="card card-tight fs14">{{ $pedido->response_message }}</div></div>@endif @if($pedido->order)<a class="btn btn-primary btn-block mt16" href="{{ route('pedidos') }}">Ver en Pedidos #{{ $pedido->order->number }}</a>@endif</div>
        @endif
    </div>
</div>
@endsection
