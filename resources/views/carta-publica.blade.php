{{--
  La carta que ve el cliente, sin sesión.

  Se sirve al celular del que está sentado en la mesa: mobile-first, una sola
  columna, sin menú lateral y sin nada que se pueda tocar por error. Reusa los
  tokens del sistema de diseño (resources/css/sistema.css) pero no su shell:
  acá no hay rail, ni barra superior, ni cambio de tema.
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>Carta · {{ $negocio }}</title>
    <meta name="description" content="Carta y precios de {{ $negocio }}">
    <meta name="theme-color" content="#070908">

    {{-- La comparten por WhatsApp más de lo que la escanean. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="Carta de {{ $negocio }}">
    <meta property="og:description" content="Mirá la carta y los precios actualizados.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])

    <style>
        body { overflow-y: auto; }

        .pub {
            max-width: 720px;
            margin: 0 auto;
            padding: 0 16px 64px;
        }

        .pub-hd {
            text-align: center;
            padding: 34px 0 20px;
        }
        .pub-hd .nombre {
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -.5px;
        }
        .pub-hd .bajada {
            color: var(--txt-2);
            font-size: 14.5px;
            margin-top: 6px;
        }
        .pub-hd .linea {
            width: 46px; height: 3px; border-radius: 2px;
            background: var(--green);
            margin: 16px auto 0;
        }

        /* Índice de categorías: queda arriba al hacer scroll, es la única
           navegación de la página. */
        .pub-nav {
            position: sticky; top: 0; z-index: 5;
            display: flex; gap: 8px;
            overflow-x: auto;
            padding: 10px 0;
            margin-bottom: 8px;
            background: var(--bg);
            border-bottom: 1px solid var(--line);
            scrollbar-width: none;
        }
        .pub-nav::-webkit-scrollbar { display: none; }
        .pub-nav a {
            flex: none;
            padding: 7px 13px;
            border: 1px solid var(--line-2);
            border-radius: 999px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--txt-2);
            white-space: nowrap;
        }
        .pub-nav a:hover { color: var(--txt); border-color: var(--green-line); }

        .pub-cat { padding-top: 26px; scroll-margin-top: 62px; }
        .pub-cat h2 {
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 12px;
        }

        .pub-item {
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }
        .pub-cat .pub-item:last-child { border-bottom: none; }

        .pub-foto {
            flex: none;
            width: 68px; height: 68px;
            border-radius: var(--r-sm);
            object-fit: contain;
            background: #fff;
            border: 1px solid var(--line);
        }

        .pub-item .txt { flex: 1; min-width: 0; }
        .pub-item .nm { font-weight: 500; }
        /* La descripción se lee entera: es el motivo por el que está. */
        .pub-item .ds { font-size: 13.5px; color: var(--txt-2); margin-top: 3px; line-height: 1.4; }
        .pub-item .vr { font-size: 13px; color: var(--txt-3); margin-top: 3px; }

        .pub-item .pr {
            flex: none;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .pub-ft {
            text-align: center;
            color: var(--txt-3);
            font-size: 12.5px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }

        /* Con foto la fila crece, así que en pantallas grandes se aprovecha
           el ancho en dos columnas. */
        @media (min-width: 620px) {
            .pub-lista { display: grid; grid-template-columns: 1fr 1fr; column-gap: 26px; }
            .pub-hd { padding-top: 48px; }
        }
    </style>
</head>
<body>

<div class="pub">

    <header class="pub-hd">
        <div class="nombre">{{ $negocio }}</div>
        @if ($mensaje !== '')
            <div class="bajada">{{ $mensaje }}</div>
        @endif
        <div class="linea"></div>
    </header>

    @if ($categorias->isEmpty())
        <p style="text-align:center;color:var(--txt-2);padding:40px 0">
            La carta está en preparación.
        </p>
    @else

        <nav class="pub-nav">
            @foreach ($categorias as $cat)
                <a href="#cat-{{ $cat->id }}">{{ $cat->name }}</a>
            @endforeach
        </nav>

        @foreach ($categorias as $cat)
            <section class="pub-cat" id="cat-{{ $cat->id }}">
                <h2>{{ $cat->name }}</h2>

                <div class="pub-lista">
                    @foreach ($cat->products as $p)
                        <div class="pub-item">
                            @if ($p->foto())
                                <img class="pub-foto" src="{{ $p->foto() }}" alt="{{ $p->name }}"
                                     loading="lazy" width="68" height="68">
                            @endif

                            <div class="txt">
                                <div class="nm">{{ $p->name }}</div>
                                @if ($p->description)
                                    <div class="ds">{{ $p->description }}</div>
                                @endif
                                @if ($p->variants->isNotEmpty())
                                    {{-- Con variantes el precio de arriba es el de la base:
                                         el detalle de cada tamaño va acá. --}}
                                    <div class="vr">
                                        {{ $p->variants->map(fn ($v) => $v->name . ' ' . \App\Support\Plata::format($v->precio()))->join(' · ') }}
                                    </div>
                                @endif
                            </div>

                            <div class="pr">@plata($p->price)</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

    @endif

    <div class="pub-ft">
        Los precios pueden cambiar sin aviso.
    </div>

</div>

</body>
</html>
