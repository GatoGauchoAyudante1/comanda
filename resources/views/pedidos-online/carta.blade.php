<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pedido online · {{ $negocio }}</title>
    <meta name="description" content="Hacé tu pedido online en {{ $negocio }}">
    <meta name="theme-color" content="#070908">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body{overflow-y:auto}.online{max-width:760px;margin:auto;padding:0 16px 120px}.online-hd{text-align:center;padding:32px 0 20px}.online-hd h1{font-size:30px;font-weight:600}.online-hd p{color:var(--txt-2);margin-top:6px}.online-nav{position:sticky;top:0;z-index:5;display:flex;gap:8px;overflow:auto;padding:10px 0;background:var(--bg);border-bottom:1px solid var(--line);scrollbar-width:none}.online-nav a{flex:none;padding:7px 13px;border:1px solid var(--line-2);border-radius:999px;color:var(--txt-2);font-size:13px}.online-cat{padding-top:26px;scroll-margin-top:60px}.online-cat h2{font-size:12.5px;letter-spacing:1.4px;text-transform:uppercase;color:var(--green);margin-bottom:12px}.online-item{padding:15px 0;border-bottom:1px solid var(--line)}.item-main{display:flex;gap:13px;align-items:center}.item-main img{width:68px;height:68px;object-fit:contain;background:#fff;border-radius:12px}.item-txt{flex:1;min-width:0}.item-name{font-weight:600}.item-desc{font-size:13.5px;color:var(--txt-2);margin-top:3px}.item-price{font-size:18px;font-weight:600;white-space:nowrap}.item-controls{display:grid;grid-template-columns:130px 1fr;gap:10px;margin-top:12px}.stepper{display:flex;align-items:center;border:1px solid var(--line-2);border-radius:12px;overflow:hidden;height:44px}.stepper button{width:42px;height:100%;border:0;background:var(--panel-2);color:var(--txt);font-size:22px}.stepper input{min-width:0;width:46px;text-align:center;background:transparent;color:var(--txt);border:0;font-weight:600}.variant{grid-column:1/-1}.online-dock{position:fixed;left:0;right:0;bottom:0;z-index:10;background:color-mix(in srgb,var(--bg) 92%,transparent);backdrop-filter:blur(12px);border-top:1px solid var(--line);padding:12px 16px}.online-dock>div{max-width:728px;margin:auto;display:flex;align-items:center;gap:14px}.online-dock .total{font-size:20px;font-weight:700}.error{max-width:728px;margin:12px auto;color:var(--amber)}@media(max-width:540px){.item-controls{grid-template-columns:122px 1fr}.online-dock .btn{padding:0 14px}}
    </style>
</head>
<body>
<form class="online" method="POST" action="{{ route('pedido-online.checkout') }}" id="pedido-form">
    @csrf
    <header class="online-hd"><h1>{{ $negocio }}</h1><p>{{ $mensaje ?: 'Elegí qué querés pedir' }}</p></header>
    @if($errors->any())<div class="notice notice-amber"><span class="dot dot-amber"></span><div>{{ $errors->first() }}</div></div>@endif
    <nav class="online-nav">@foreach($categorias as $cat)<a href="#cat-{{ $cat->id }}">{{ $cat->name }}</a>@endforeach</nav>
    @php($indice = 0)
    @forelse($categorias as $cat)
        <section class="online-cat" id="cat-{{ $cat->id }}"><h2>{{ $cat->name }}</h2>
        @foreach($cat->products as $producto)
            <article class="online-item" data-item data-base-price="{{ $producto->price }}">
                <div class="item-main">
                    @if($producto->foto())<img src="{{ $producto->foto() }}" alt="">@endif
                    <div class="item-txt"><div class="item-name">{{ $producto->name }}</div>@if($producto->description)<div class="item-desc">{{ $producto->description }}</div>@endif</div>
                    <div class="item-price" data-price>@plata($producto->price)</div>
                </div>
                <div class="item-controls">
                    @if($producto->variants->isNotEmpty())
                        <select class="inp inp-sm variant" name="lineas[{{ $indice }}][variant_id]" data-variant>
                            @foreach($producto->variants as $variante)<option value="{{ $variante->id }}" data-price="{{ $producto->price + $variante->price_delta }}">{{ $variante->name }} · @plata($producto->price + $variante->price_delta)</option>@endforeach
                        </select>
                    @endif
                    <div class="stepper"><button type="button" data-minus aria-label="Quitar">−</button><input name="lineas[{{ $indice }}][qty]" value="0" readonly inputmode="numeric" data-qty><button type="button" data-plus aria-label="Agregar">+</button></div>
                    <input class="inp inp-sm" name="lineas[{{ $indice }}][notes]" maxlength="120" placeholder="Observación (sin sal, etc.)">
                    <input type="hidden" name="lineas[{{ $indice }}][product_id]" value="{{ $producto->id }}">
                </div>
            </article>
            @php($indice++)
        @endforeach
        </section>
    @empty
        <p class="t-mute" style="text-align:center;padding:40px">La carta está en preparación.</p>
    @endforelse
</form>
<div class="online-dock"><div><div class="grow"><div class="fs13 t-mute"><span data-count>0</span> productos</div><div class="total" data-total>$0</div></div><button class="btn btn-primary" type="submit" form="pedido-form" data-submit disabled>Enviar pedido</button></div></div>
<script>
const pesos = c => '$' + Math.round(c / 100).toLocaleString('es-AR');
const items = [...document.querySelectorAll('[data-item]')];
function actualizar(){let cantidad=0,total=0;items.forEach(item=>{const qty=Number(item.querySelector('[data-qty]').value);const variant=item.querySelector('[data-variant]');const precio=variant?Number(variant.selectedOptions[0].dataset.price):Number(item.dataset.basePrice);cantidad+=qty;total+=qty*precio;item.querySelector('[data-price]').textContent=pesos(precio)});document.querySelector('[data-count]').textContent=cantidad;document.querySelector('[data-total]').textContent=pesos(total);document.querySelector('[data-submit]').disabled=!cantidad}
document.addEventListener('click',e=>{const item=e.target.closest('[data-item]');if(!item)return;const qty=item.querySelector('[data-qty]');if(e.target.closest('[data-plus]'))qty.value=Math.min(99,Number(qty.value)+1);if(e.target.closest('[data-minus]'))qty.value=Math.max(0,Number(qty.value)-1);actualizar()});
document.addEventListener('change',actualizar);
document.getElementById('pedido-form').addEventListener('submit',()=>items.forEach(item=>{if(Number(item.querySelector('[data-qty]').value)===0)item.querySelectorAll('input,select').forEach(c=>c.disabled=true)}));
</script>
</body></html>
